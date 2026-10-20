<?php

namespace Tests\Feature;

use App\Mail\CustomerLoginCode;
use App\Mail\ReservationConfirmed;
use App\Models\AiSession;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MyPageTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));

        $this->customer = Customer::create([
            'name' => 'テスト花子', 'phone' => '090-1111-2222', 'birthday' => '1990-04-01',
            'email' => 'hanako@example.com', 'email2' => 'other@example.com',
        ]);
    }

    private function loginStep1(array $override = [])
    {
        return $this->post('/my/login', array_merge(['phone' => '09011112222', 'birthday' => '1990-04-01'], $override));
    }

    // 確認コードを送って、メールに書かれたコードを返す
    private function requestCode(): string
    {
        $this->post('/my/verify/send')->assertRedirect(route('my.verify'));
        $code = null;
        Mail::assertSent(CustomerLoginCode::class, function (CustomerLoginCode $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function loginFully(): void
    {
        $this->loginStep1();
        $this->post('/my/verify', ['code' => $this->requestCode()])->assertRedirect(route('my.index'));
    }

    public function test_wrong_birthday_is_rejected_with_the_same_message(): void
    {
        $wrongBirthday = $this->loginStep1(['birthday' => '1990-04-02'])->assertSessionHasErrors('phone');
        $unknownPhone = $this->loginStep1(['phone' => '08000000000'])->assertSessionHasErrors('phone');

        $this->assertSame(
            session()->get('errors')->first('phone'),
            '電話番号または生年月日が正しくありません。生年月日を登録していない方は、下の「こちらから登録」をご利用ください。'
        );
        $this->get('/my')->assertRedirect(route('my.login'));
    }

    public function test_login_is_limited_per_phone_number(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->loginStep1(['birthday' => '2000-01-01']);
        }

        $this->loginStep1()->assertSessionHasErrors('phone');
        $this->get('/my')->assertRedirect(route('my.login'));
    }

    public function test_history_needs_the_email_code(): void
    {
        $this->loginStep1()->assertRedirect(route('my.verify'));

        // 1段階目だけでは、履歴は見られない
        $this->get('/my')->assertRedirect(route('my.verify'));

        $code = $this->requestCode();
        Mail::assertSent(CustomerLoginCode::class, fn ($mail) => $mail->hasTo('hanako@example.com') && ! $mail->hasTo('other@example.com'));

        $this->post('/my/verify', ['code' => $code === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post('/my/verify', ['code' => $code])->assertRedirect(route('my.index'));
        $this->get('/my')->assertOk()->assertSee('テスト花子 様');

        // 使ったコードは、もう使えない
        $this->post('/my/logout');
        $this->loginStep1();
        $this->post('/my/verify', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_code_is_invalid_after_too_many_mistakes(): void
    {
        $this->loginStep1();
        $code = $this->requestCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/my/verify', ['code' => $wrong]);
        }

        $this->post('/my/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->get('/my')->assertRedirect(route('my.verify'));
    }

    public function test_code_expires(): void
    {
        $this->loginStep1();
        $code = $this->requestCode();
        $this->travel(11)->minutes();

        $this->post('/my/verify', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_verification_expires_after_30_minutes(): void
    {
        $this->loginFully();
        $this->travel(31)->minutes();

        $this->get('/my')->assertRedirect(route('my.verify'));
    }

    public function test_customer_without_email_cannot_get_a_code(): void
    {
        $this->customer->update(['email' => null]);
        $this->loginStep1();

        $this->get('/my/verify')->assertOk()->assertSee('メールアドレスが登録されていないため');
        $this->post('/my/verify/send');

        Mail::assertNotSent(CustomerLoginCode::class);
    }

    public function test_history_shows_only_own_data_and_hides_staff_memo(): void
    {
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $this->customer->records()->create([
            'performed_on' => '2026-09-01', 'menu' => 'カラー',
            'concerns' => ['dryness', 'gray'], 'concern_note' => '毛先のまとまり',
            'materials' => '秘密の薬剤', 'memo' => 'スタッフだけのメモ',
        ]);
        Reservation::create(['customer_id' => $this->customer->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-10 10:00', 'status' => 'confirmed', 'source' => 'online']);
        $session = AiSession::create(['status' => 'completed', 'customer_id' => $this->customer->id, 'result_menu_id' => $menu->id, 'result_menu' => 'カット']);
        $session->messages()->create(['role' => 'user', 'content' => '髪が広がります']);

        $other = Customer::create(['name' => '別の人', 'phone' => '08099998888']);
        $other->records()->create(['performed_on' => '2026-09-02', 'menu' => '別の人のパーマ']);

        $this->loginFully();

        $this->get('/my')->assertOk()
            ->assertSee('カラー')->assertSee('乾燥・パサつき')->assertSee('白髪')->assertSee('毛先のまとまり')
            ->assertSee('2026年10月10日 10:00')
            ->assertSee('髪が広がります')
            ->assertDontSee('秘密の薬剤')->assertDontSee('スタッフだけのメモ')
            ->assertDontSee('別の人のパーマ');
    }

    public function test_next_parameter_cannot_redirect_outside(): void
    {
        $this->loginStep1(['next' => 'https://evil.example.com'])->assertRedirect(route('my.verify'));
    }

    public function test_booking_with_simple_login_skips_name_and_phone(): void
    {
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $day = today()->addDays(3);
        BusinessHour::create(['weekday' => $day->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);

        $this->loginStep1(['next' => 'book', 'menu_id' => $menu->id])
            ->assertRedirect(route('booking.index', ['menu_id' => $menu->id]));

        $this->get(route('booking.index', ['menu_id' => $menu->id]))->assertOk()
            ->assertSee('テスト花子 様としてご予約します')
            ->assertSee('h****@example.com')
            ->assertDontSee('name="phone"', false);

        $this->post('/book', ['menu_id' => $menu->id, 'date' => $day->format('Y-m-d'), 'time' => '10:00'])
            ->assertRedirect(route('booking.complete'));

        $reservation = Reservation::first();
        $this->assertSame($this->customer->id, $reservation->customer_id);
        $this->assertFalse($reservation->needs_review);
        $this->assertSame(1, Customer::count());
        Mail::assertSent(ReservationConfirmed::class, fn ($mail) => $mail->hasTo('hanako@example.com'));
    }

    public function test_new_customer_can_register_birthday_when_booking(): void
    {
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $day = today()->addDays(3);
        BusinessHour::create(['weekday' => $day->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);

        $this->post('/book', [
            'menu_id' => $menu->id, 'date' => $day->format('Y-m-d'), 'time' => '10:00',
            'name' => '新しい人', 'phone' => '080-2222-3333', 'email' => 'new@example.com', 'birthday' => '1995-05-05',
        ])->assertRedirect(route('booking.complete'));

        $this->assertSame('1995-05-05', Customer::where('name', '新しい人')->first()->birthday->format('Y-m-d'));
    }

    public function test_admin_can_save_and_clear_counseling_sheet(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('records.store', $this->customer), [
            'performed_on' => '2026-10-01', 'menu' => 'カラー',
            'concerns' => ['dryness', 'scalp_itch'], 'concern_note' => '前回より乾燥',
        ])->assertRedirect();

        $record = $this->customer->records()->first();
        $this->assertSame(['dryness', 'scalp_itch'], $record->concerns);

        $this->post(route('records.store', $this->customer), [
            'performed_on' => '2026-10-02', 'menu' => 'カット', 'concerns' => ['unknown'],
        ])->assertSessionHasErrors('concerns.0');

        $this->put(route('records.update', $record), ['performed_on' => '2026-10-01', 'menu' => 'カラー'])->assertRedirect();
        $this->assertSame([], $record->fresh()->concerns);
    }

    public function test_customer_without_birthday_can_register_it_with_email_code(): void
    {
        $this->customer->update(['birthday' => null]);

        $this->post('/my/birthday/send', ['phone' => '090-1111-2222'])->assertRedirect(route('my.birthday'));
        $code = null;
        Mail::assertSent(CustomerLoginCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('hanako@example.com');
        });

        $this->post('/my/birthday', ['code' => $code === '000000' ? '111111' : '000000', 'birthday' => '1990-04-01'])
            ->assertSessionHasErrors('code');
        $this->assertNull($this->customer->fresh()->birthday);

        $this->post('/my/birthday', ['code' => $code, 'birthday' => '1990-04-01'])->assertRedirect(route('my.index'));
        $this->assertSame('0401', $this->customer->fresh()->karte_number);
        $this->get('/my')->assertOk()->assertSee('生年月日を登録しました');
    }

    public function test_birthday_registration_does_not_reveal_or_overwrite(): void
    {
        // 生年月日が登録済みの人：コードは送らず、画面は同じ
        $this->post('/my/birthday/send', ['phone' => '090-1111-2222'])->assertRedirect(route('my.birthday'));
        $this->get('/my/birthday')->assertSee('確認コードをお送りしました');
        $this->post('/my/birthday', ['code' => '123456', 'birthday' => '2000-01-01'])->assertSessionHasErrors('code');

        // 登録のない電話番号も、同じ画面
        $this->post('/my/birthday/send', ['phone' => '08000000000'])->assertRedirect(route('my.birthday'));

        Mail::assertNotSent(CustomerLoginCode::class);
        $this->assertSame('1990-04-01', $this->customer->fresh()->birthday->format('Y-m-d'));
    }

    public function test_counseling_only_for_own_upcoming_reservation(): void
    {
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        $own = Reservation::create(['customer_id' => $this->customer->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-10 10:00', 'status' => 'confirmed', 'source' => 'online']);
        $other = Customer::create(['name' => '別の人', 'phone' => '08099998888']);
        $othersReservation = Reservation::create(['customer_id' => $other->id, 'menu_id' => $menu->id, 'scheduled_at' => '2026-10-11 10:00', 'status' => 'confirmed', 'source' => 'online']);

        // ログインしていなければ入力できない
        $this->get(route('my.counseling', $own))->assertRedirect(route('my.login', ['next' => 'book']));

        $this->loginStep1();
        $this->get(route('my.counseling.done', $own))->assertRedirect(route('booking.index')); // まだ送信していない
        $this->put(route('my.counseling', $own), ['concerns' => ['dryness'], 'concern_note' => '毛先が気になる'])
            ->assertRedirect(route('my.counseling.done', $own));
        $this->get(route('my.counseling.done', $own))->assertOk()
            ->assertSee('事前カウンセリングを送信しました')
            ->assertSee('乾燥・パサつき')
            ->assertSee('毛先が気になる');
        $this->assertSame(['dryness'], $own->fresh()->concerns);
        $this->assertNotNull($own->fresh()->counseled_at);

        // 他人の予約には入力できない
        $this->get(route('my.counseling', $othersReservation))->assertRedirect();
        $this->put(route('my.counseling', $othersReservation), ['concerns' => ['dryness']])->assertRedirect();
        $this->assertNull($othersReservation->fresh()->counseled_at);
        $othersReservation->update(['counseled_at' => now()]);
        $this->get(route('my.counseling.done', $othersReservation))->assertRedirect(route('booking.index'));

        // 管理者が対応記録を作るとき、事前カウンセリングが入っている
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(11, 0));
        $this->actingAs(User::factory()->create());
        $this->get(route('records.create', $this->customer))->assertOk()
            ->assertSee('毛先が気になる')
            ->assertSee('value="dryness" checked', false);
        $this->get(route('reservations.index', ['past' => 1]))->assertOk()->assertSee('事前カウンセリング');
    }
}
