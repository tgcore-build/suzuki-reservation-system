<?php

namespace Tests\Feature;

use App\Mail\CustomerLoginCode;
use App\Mail\ReservationReminder;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LineTest extends TestCase
{
    use RefreshDatabase;

    private const USER_ID = 'U0123456789abcdef0123456789abcdef';

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        config([
            'line.login_channel_id' => '1234567890',
            'line.login_channel_secret' => 'login-secret',
            'line.messaging_access_token' => 'messaging-token',
        ]);
        $this->customer = Customer::create([
            'name' => 'テスト花子', 'phone' => '09011112222', 'birthday' => '1990-04-01', 'email' => 'hanako@example.com',
        ]);
    }

    // 電話番号＋生年月日、確認コードまで済ませる（利用履歴を見られる状態）
    private function loginFully(): void
    {
        $this->post('/my/login', ['phone' => '09011112222', 'birthday' => '1990-04-01']);
        $this->post('/my/verify/send');
        $code = null;
        Mail::assertSent(CustomerLoginCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->post('/my/verify', ['code' => $code])->assertRedirect(route('my.index'));
    }

    private function fakeLine(bool $friend = true, string $userId = self::USER_ID): void
    {
        Http::fake([
            'api.line.me/oauth2/v2.1/token' => Http::response(['access_token' => 'user-token', 'id_token' => 'id.token.here']),
            'api.line.me/oauth2/v2.1/verify' => Http::response(['sub' => $userId]),
            'api.line.me/friendship/v1/status' => Http::response(['friendFlag' => $friend]),
            'api.line.me/v2/bot/message/push' => Http::response([]),
        ]);
    }

    // LINE の画面へ移動して、state を受け取る
    private function startLink(): string
    {
        $location = $this->get('/my/line')->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://access.line.me/oauth2/v2.1/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('1234567890', $query['client_id']);
        $this->assertSame(route('my.line.callback'), $query['redirect_uri']);
        $this->assertSame('normal', $query['bot_prompt']);

        return $query['state'];
    }

    public function test_link_button_is_shown_only_when_configured(): void
    {
        $this->loginFully();
        $this->get('/my')->assertSee('LINE と連携する');

        config(['line.login_channel_id' => null]);
        $this->get('/my')->assertDontSee('LINE と連携する');
        $this->get('/my/line')->assertRedirect(route('my.index'));
    }

    public function test_linking_needs_the_email_code_step(): void
    {
        $this->post('/my/login', ['phone' => '09011112222', 'birthday' => '1990-04-01']); // 1段階目だけ
        $this->get('/my/line')->assertRedirect(route('my.verify'));
        $this->get('/my/line/callback?code=abc&state=xyz')->assertRedirect(route('my.verify'));
        $this->assertNull($this->customer->fresh()->line_user_id);
    }

    public function test_link_saves_line_user_id(): void
    {
        $this->fakeLine();
        $this->loginFully();
        $state = $this->startLink();

        $this->get("/my/line/callback?code=abc&state={$state}")->assertRedirect(route('my.index'));

        $this->assertSame(self::USER_ID, $this->customer->fresh()->line_user_id);
        $this->get('/my')->assertSee('LINE と連携しました。次回から')->assertSee('連携済み');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth2/v2.1/verify') && $request['client_id'] === '1234567890' && filled($request['nonce']));
    }

    public function test_not_friend_is_told_to_add_official_account(): void
    {
        $this->fakeLine(friend: false);
        $this->loginFully();
        $state = $this->startLink();

        $this->get("/my/line/callback?code=abc&state={$state}");

        $this->get('/my')->assertSee('公式アカウントを友だち追加してください');
    }

    public function test_wrong_state_is_rejected(): void
    {
        $this->fakeLine();
        $this->loginFully();
        $this->startLink();

        $this->get('/my/line/callback?code=abc&state=wrong')->assertRedirect(route('my.index'));

        $this->assertNull($this->customer->fresh()->line_user_id);
        Http::assertNothingSent();
    }

    public function test_cancel_on_line_screen(): void
    {
        $this->loginFully();
        $state = $this->startLink();

        $this->get("/my/line/callback?error=access_denied&state={$state}")->assertRedirect(route('my.index'));
        $this->get('/my')->assertSee('LINE との連携をやめました。');
    }

    public function test_one_line_account_cannot_link_two_customers(): void
    {
        Customer::create(['name' => '別の人', 'phone' => '08099998888', 'line_user_id' => self::USER_ID]);
        $this->fakeLine();
        $this->loginFully();
        $state = $this->startLink();

        $this->get("/my/line/callback?code=abc&state={$state}");

        $this->assertNull($this->customer->fresh()->line_user_id);
        $this->get('/my')->assertSee('別のお客様の登録に連携されています');
    }

    public function test_unlink(): void
    {
        $this->customer->update(['line_user_id' => self::USER_ID, 'line_linked_at' => now()]);
        $this->loginFully();

        $this->post('/my/line/unlink')->assertRedirect(route('my.index'));

        $this->assertNull($this->customer->fresh()->line_user_id);
    }

    private function reservationTomorrow(array $attributes = []): Reservation
    {
        $menu = Menu::firstOrCreate(['name' => 'カット'], ['price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);

        return Reservation::create(array_merge([
            'customer_id' => $this->customer->id, 'menu_id' => $menu->id,
            'scheduled_at' => today()->addDay()->setTime(10, 0), 'source' => 'online', 'status' => 'confirmed',
        ], $attributes));
    }

    public function test_reminder_is_sent_by_line_when_linked(): void
    {
        $this->fakeLine();
        $this->customer->update(['line_user_id' => self::USER_ID]);
        $reservation = $this->reservationTomorrow();

        $this->artisan('reminders:send')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v2/bot/message/push')
            && $request->hasHeader('Authorization', 'Bearer messaging-token')
            && $request->hasHeader('X-Line-Retry-Key')
            && $request['to'] === self::USER_ID
            && str_contains($request['messages'][0]['text'], 'テスト花子 様')
            && str_contains($request['messages'][0]['text'], 'カット'));
        Mail::assertNotSent(ReservationReminder::class);
        $this->assertSame('line', $reservation->fresh()->reminder_channel);
    }

    public function test_reminder_falls_back_to_email_when_line_fails(): void
    {
        Http::fake(['api.line.me/v2/bot/message/push' => Http::response(['message' => 'blocked'], 400)]);
        $this->customer->update(['line_user_id' => self::USER_ID]);
        $reservation = $this->reservationTomorrow();

        $this->artisan('reminders:send')->assertSuccessful();

        Mail::assertSent(ReservationReminder::class, fn ($mail) => $mail->hasTo('hanako@example.com'));
        $this->assertSame('email', $reservation->fresh()->reminder_channel);
    }

    public function test_reminder_uses_email_when_not_linked_or_line_not_configured(): void
    {
        Http::fake();
        $reservation = $this->reservationTomorrow();
        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame('email', $reservation->fresh()->reminder_channel);

        config(['line.messaging_access_token' => null]);
        $this->customer->update(['line_user_id' => self::USER_ID]);
        $second = $this->reservationTomorrow(['scheduled_at' => today()->addDay()->setTime(14, 0)]);
        $this->artisan('reminders:send')->assertSuccessful();
        $this->assertSame('email', $second->fresh()->reminder_channel);

        Http::assertNothingSent();
    }
}
