<?php

namespace Tests\Feature;

use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_page_shows_required_items(): void
    {
        config(['salon.contact' => "電話 000-0000-0000\nメール info@example.com"]);

        $this->get('/privacy')->assertOk()
            ->assertSee('利用目的')
            ->assertSee('お名前、電話番号、メールアドレス')
            ->assertSee('生年月日')
            ->assertSee('お悩み')
            ->assertSee('Gemini')
            ->assertSee('保存期間')
            ->assertSee('電話 000-0000-0000')
            ->assertSee('info@example.com');
    }

    public function test_contact_falls_back_when_not_set(): void
    {
        config(['salon.contact' => null]);

        $this->get('/privacy')->assertOk()->assertSee('お問い合わせください');
    }

    public function test_forms_link_to_policy(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        BusinessHour::create(['weekday' => today()->addDay()->dayOfWeek, 'is_open' => true, 'open_time' => '10:00', 'close_time' => '19:00']);
        $link = 'href="' . route('privacy') . '"';

        $this->get('/book')->assertSee($link, false);                                    // 予約（フッター）
        $this->get(route('booking.index', ['menu_id' => $menu->id]))                    // 予約の入力フォーム
            ->assertSee('プライバシーポリシー</a>に沿って取り扱います', false);
        $this->get('/my/login')->assertSee('プライバシーポリシー</a>に沿って取り扱います', false);
        $this->get('/ai')->assertSee($link, false);

        // 事前カウンセリングのフォーム
        $customer = Customer::create(['name' => 'テスト花子', 'phone' => '09011112222', 'birthday' => '1990-04-01']);
        $reservation = Reservation::create(['customer_id' => $customer->id, 'menu_id' => $menu->id, 'scheduled_at' => today()->addDay()->setTime(10, 0), 'source' => 'online', 'status' => 'confirmed']);
        $this->post('/my/login', ['phone' => '09011112222', 'birthday' => '1990-04-01']);
        $this->get(route('my.counseling', $reservation))->assertSee('プライバシーポリシー</a>に沿って取り扱います', false);
    }

    public function test_policy_does_not_hardcode_shop_name(): void
    {
        config(['app.name' => '〇〇']);

        $this->get('/privacy')->assertSee('〇〇（以下「当店」）');
    }

    public function test_analysis_purpose_and_retention_are_stated(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('メニューやサービスの改善のための分析')
            ->assertSee('個人が分からない形で集計')
            ->assertSee('ご利用が終わったあとも保存することがあります')
            ->assertSee('削除のご依頼があれば、削除します。');
    }
}
