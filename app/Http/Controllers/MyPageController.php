<?php

namespace App\Http\Controllers;

use App\Mail\CustomerLoginCode;
use App\Models\Customer;
use App\Models\Record;
use App\Models\Reservation;
use App\Services\CustomerLogin;
use App\Services\Line\LineClient;
use App\Services\Line\LineException;
use App\Support\BirthdayInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// お客様向け：簡易ログインと利用履歴
class MyPageController extends Controller
{
    private const BIRTHDAY_KEY = 'customer_login.birthday_customer_id';
    private const LINE_KEY = 'customer_login.line';

    public function __construct(private CustomerLogin $login)
    {
    }

    // 1段階目：電話番号＋生年月日
    public function loginForm(Request $request): View
    {
        return view('my.login', [
            'next' => $this->next($request),
            'menuId' => (int) $request->query('menu_id') ?: null,
        ]);
    }

    public function login(Request $request): RedirectResponse|Response
    {
        BirthdayInput::prepare($request);

        // エラーのときは、ログイン画面へ移動し直さず、その場でエラー付きの画面を返す。
        // 移動し直すと、エラーは「次の1回の表示」でしか出ないため、ブラウザ（Safari など）が
        // 同じ画面を2回読み込むと、エラーが消えてしまうことがある。行き先（予約画面から来たか）も残す
        $back = function (array $errors) use ($request) {
            $request->flashOnly('phone', 'birthday'); // 入力した値を、この画面の old() で使う

            return response()->view('my.login', [
                'next' => $this->next($request),
                'menuId' => (int) $request->input('menu_id') ?: null,
                'errors' => (new ViewErrorBag)->put('default', new MessageBag($errors)),
            ], 422);
        };

        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'max:20'],
            'birthday' => ['required', 'date_format:Y-m-d'],
        ], ['birthday.date_format' => BirthdayInput::MESSAGE], [
            'phone' => '電話番号',
            'birthday' => '生年月日',
        ]);
        if ($validator->fails()) {
            return $back($validator->errors()->toArray());
        }
        $data = $validator->validated();

        // 同じ電話番号で何度も試せないようにする（10分に5回まで）
        $limitKey = 'customer-login:' . preg_replace('/\D/', '', $data['phone']);
        if (RateLimiter::tooManyAttempts($limitKey, 5)) {
            return $back(['phone' => 'ログインの試行回数が多すぎます。しばらくしてから、もう一度お試しください。']);
        }

        $customer = $this->login->findByPhoneAndBirthday($data['phone'], $data['birthday']);
        if (! $customer) {
            RateLimiter::hit($limitKey, 600);
            Log::warning('お客様ログイン失敗', ['ip' => $request->ip()]);

            // 電話番号が登録されているかどうかが分からないように、同じメッセージにする
            return $back(['phone' => '電話番号または生年月日が正しくありません。生年月日を登録していない方は、下の「こちらから登録」をご利用ください。']);
        }

        RateLimiter::clear($limitKey);
        $this->login->login($request, $customer);

        return $this->next($request) === 'book'
            ? redirect()->route('booking.index', array_filter(['menu_id' => (int) $request->input('menu_id') ?: null]))
            : redirect()->route('my.verify');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->login->logout($request);

        return redirect()->route('booking.index');
    }

    // 2段階目：メールに届くコード
    public function verifyForm(Request $request): View|RedirectResponse
    {
        $customer = $this->login->customer($request);
        if (! $customer) {
            return redirect()->route('my.login');
        }
        if ($this->login->isVerified($request)) {
            return redirect()->route('my.index');
        }

        return view('my.verify', [
            'customer' => $customer,
            'maskedEmail' => CustomerLogin::maskEmail($customer->email),
            'codeSent' => (bool) session('code_sent'),
        ]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $customer = $this->login->customer($request);
        if (! $customer) {
            return redirect()->route('my.login');
        }
        // コードは、お店に登録されている1つ目のアドレスにだけ送る（予約のときに入力されたアドレスには送らない）
        if (! $customer->email) {
            return redirect()->route('my.verify');
        }

        $limitKey = 'customer-login-code-send:' . $customer->id;
        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            return redirect()->route('my.verify')->withErrors([
                'code' => 'コードの送信回数が多すぎます。しばらくしてから、もう一度お試しください。',
            ]);
        }
        RateLimiter::hit($limitKey, 600);

        Mail::to($customer->email)->send(new CustomerLoginCode($customer->name, $this->login->issueCode($customer)));

        return redirect()->route('my.verify')->with('code_sent', true);
    }

    public function verify(Request $request): RedirectResponse
    {
        $customer = $this->login->customer($request);
        if (! $customer) {
            return redirect()->route('my.login');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']], [], ['code' => '確認コード']);

        if (! $this->login->verifyCode($request, $customer, $data['code'])) {
            Log::warning('お客様の確認コードの誤り', ['customer_id' => $customer->id, 'ip' => $request->ip()]);

            return redirect()->route('my.verify')->with('code_sent', true)->withErrors([
                'code' => '確認コードが正しくないか、有効期間が過ぎています。まちがえが続いた場合は、コードを送り直してください。',
            ]);
        }

        return redirect()->route('my.index');
    }

    // 利用履歴（2段階目まで確認できた人だけ）
    public function index(Request $request): View|RedirectResponse
    {
        $customer = $this->login->customer($request);
        if (! $customer) {
            return redirect()->route('my.login');
        }
        if (! $this->login->isVerified($request)) {
            return redirect()->route('my.verify');
        }

        return view('my.index', [
            'customer' => $customer,
            'lineEnabled' => app(LineClient::class)->loginEnabled(),
            'upcoming' => $customer->reservations()->with('menu')
                ->where('status', 'confirmed')->where('scheduled_at', '>=', now())
                ->orderBy('scheduled_at')->get(),
            'pastReservations' => $customer->reservations()->with('menu')
                ->where('status', '!=', 'cancelled')->where('scheduled_at', '<', now())
                ->orderByDesc('scheduled_at')->limit(20)->get(),
            'records' => $customer->records()->orderByDesc('performed_on')->get(),
            'aiSessions' => $customer->aiSessions()->with(['resultMenu', 'messages'])
                ->where('status', 'completed')->orderByDesc('created_at')->get(),
        ]);
    }

    // LINE 連携：LINE のログイン画面へ（利用履歴を見られる人＝確認コードで本人確認できた人だけ）
    public function lineRedirect(Request $request, LineClient $line): RedirectResponse
    {
        if (! $line->loginEnabled()) {
            return redirect()->route('my.index');
        }
        if (! $this->login->customer($request) || ! $this->login->isVerified($request)) {
            return redirect()->route('my.verify');
        }

        // なりすましを防ぐための、毎回変わる値（戻ってきたときに同じか確かめる）
        $state = Str::random(40);
        $nonce = Str::random(40);
        $request->session()->put(self::LINE_KEY, ['state' => $state, 'nonce' => $nonce]);

        return redirect()->away($line->authorizeUrl(route('my.line.callback'), $state, $nonce));
    }

    // LINE から戻ってきたとき
    public function lineCallback(Request $request, LineClient $line): RedirectResponse
    {
        $saved = $request->session()->pull(self::LINE_KEY);
        $customer = $this->login->customer($request);
        if (! $customer || ! $this->login->isVerified($request)) {
            return redirect()->route('my.verify');
        }

        if ($request->filled('error')) {
            return redirect()->route('my.index')->with('status', 'LINE との連携をやめました。');
        }
        if (! $saved || ! is_string($request->query('state')) || ! hash_equals($saved['state'], $request->query('state')) || ! $request->filled('code')) {
            return redirect()->route('my.index')->with('line_error', 'LINE との連携に失敗しました。もう一度お試しください。');
        }

        try {
            $user = $line->fetchUser((string) $request->query('code'), route('my.line.callback'), $saved['nonce']);
        } catch (LineException $e) {
            report($e);

            return redirect()->route('my.index')->with('line_error', 'LINE との連携に失敗しました。もう一度お試しください。');
        }

        // 1つの LINE アカウントは、1人のお客様にだけ連携できる
        if (Customer::where('line_user_id', $user['user_id'])->whereKeyNot($customer->id)->exists()) {
            return redirect()->route('my.index')->with('line_error', 'この LINE アカウントは、別のお客様の登録に連携されています。お店にお問い合わせください。');
        }

        $customer->update(['line_user_id' => $user['user_id'], 'line_linked_at' => now()]);
        Log::info('LINE 連携', ['customer_id' => $customer->id]);

        return redirect()->route('my.index')->with('status', $user['is_friend']
            ? 'LINE と連携しました。次回から、ご予約のリマインドを LINE でお送りします。'
            : 'LINE と連携しました。リマインドを LINE で受け取るには、公式アカウントを友だち追加してください（追加するまでは、メールでお送りします）。');
    }

    public function lineUnlink(Request $request): RedirectResponse
    {
        $customer = $this->login->customer($request);
        if (! $customer || ! $this->login->isVerified($request)) {
            return redirect()->route('my.verify');
        }

        $customer->update(['line_user_id' => null, 'line_linked_at' => null]);
        Log::info('LINE 連携の解除', ['customer_id' => $customer->id]);

        return redirect()->route('my.index')->with('status', 'LINE との連携を解除しました。リマインドは、メールでお送りします。');
    }

    // 来店記録の写真（確認コードまで済ませた本人だけ）
    public function recordPhoto(Request $request, Record $record, string $field): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $customer = $this->login->customer($request);
        abort_unless($customer && $this->login->isVerified($request) && $record->customer_id === $customer->id, 403);

        return RecordController::photoResponse($record, $field);
    }

    // 事前カウンセリング（ご来店前に、お悩みやご要望を入力する）。かんたんログインした本人の、これからの予約だけ
    public function counselingForm(Request $request, Reservation $reservation): View|RedirectResponse
    {
        if (! $this->ownUpcoming($request, $reservation)) {
            return redirect()->route('my.login', ['next' => 'book']);
        }

        return view('my.counseling', ['reservation' => $reservation->load('menu')]);
    }

    public function counseling(Request $request, Reservation $reservation): RedirectResponse
    {
        if (! $this->ownUpcoming($request, $reservation)) {
            return redirect()->route('my.login', ['next' => 'book']);
        }

        $data = $request->validate([
            'concerns' => ['nullable', 'array'],
            'concerns.*' => ['string', Rule::in(array_keys(config('salon.concerns')))],
            'concern_note' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'concerns' => 'お悩み',
            'concerns.*' => 'お悩み',
            'concern_note' => 'お悩み・ご要望',
        ]);

        $reservation->update([
            'concerns' => array_values($data['concerns'] ?? []),
            'concern_note' => $data['concern_note'] ?? null,
            'counseled_at' => now(),
        ]);

        return redirect()->route('my.counseling.done', $reservation);
    }

    // 事前カウンセリングの送信完了
    public function counselingDone(Request $request, Reservation $reservation): View|RedirectResponse
    {
        if (! $this->ownUpcoming($request, $reservation) || ! $reservation->counseled_at) {
            return redirect()->route('booking.index');
        }

        return view('my.counseling-done', ['reservation' => $reservation->load('menu')]);
    }

    // 生年月日が未登録の方：登録メールに届くコードで本人と確認してから、生年月日を登録する
    public function birthdayForm(Request $request): View
    {
        return view('my.birthday', [
            'pending' => $request->session()->has(self::BIRTHDAY_KEY), // 送った・送らないに関係なく、コードの入力画面にする
        ]);
    }

    public function birthdaySendCode(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:20']], [], ['phone' => '電話番号']);

        $limitKey = 'customer-birthday-code:' . preg_replace('/\D/', '', $data['phone']);
        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            throw ValidationException::withMessages([
                'phone' => 'コードの送信回数が多すぎます。しばらくしてから、もう一度お試しください。',
            ]);
        }
        RateLimiter::hit($limitKey, 600);

        // 登録があって、メールがあり、生年月日が未登録の方にだけ送る。どの場合も同じ画面にして、登録の有無が分からないようにする
        $customer = $this->login->findByPhone($data['phone']);
        if ($customer && $customer->email && ! $customer->birthday) {
            Mail::to($customer->email)->send(new CustomerLoginCode($customer->name, $this->login->issueCode($customer)));
            $request->session()->put(self::BIRTHDAY_KEY, $customer->id);
        } else {
            $request->session()->put(self::BIRTHDAY_KEY, 0);
        }

        return redirect()->route('my.birthday');
    }

    public function birthdayRegister(Request $request): RedirectResponse
    {
        BirthdayInput::prepare($request);
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'birthday' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'],
        ], ['birthday.date_format' => BirthdayInput::MESSAGE], ['code' => '確認コード', 'birthday' => '生年月日']);

        $customer = Customer::find((int) $request->session()->get(self::BIRTHDAY_KEY));
        if (! $customer || $customer->birthday || ! $this->login->checkCode($customer, $data['code'])) {
            return redirect()->route('my.birthday')->withInput($request->only('birthday'))->withErrors([
                'code' => '確認コードが正しくないか、有効期間が過ぎています。',
            ]);
        }

        $customer->update(['birthday' => $data['birthday']]);
        $request->session()->forget(self::BIRTHDAY_KEY);
        $this->login->markVerified($request, $customer);

        return redirect()->route('my.index')->with('status', '生年月日を登録しました。次回から、電話番号と生年月日でログインできます。');
    }

    private function ownUpcoming(Request $request, Reservation $reservation): bool
    {
        $customer = $this->login->customer($request);

        return $customer
            && $reservation->customer_id === $customer->id
            && $reservation->status === 'confirmed'
            && $reservation->scheduled_at->isFuture();
    }

    // ログインのあと、どこへ戻るか（決まった行き先だけにして、外部サイトへ飛ばされないようにする）
    private function next(Request $request): string
    {
        return $request->input('next') === 'book' ? 'book' : 'history';
    }
}
