<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAdviceTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.gemini.key' => 'test-key', 'ai.gemini.model' => 'gemini-test', 'ai.daily_limit' => 100]);

        $this->menu = Menu::create([
            'name' => 'うる艶トリートメント',
            'price' => 5500,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function fakeGemini(array $answer): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($answer, JSON_UNESCAPED_UNICODE)]]],
                ]],
            ]),
        ]);
    }

    public function test_page_shows_the_form(): void
    {
        $this->get('/ai')->assertOk()->assertSee('メニュー選びのご相談')->assertSee('個人が特定できる情報');
    }

    public function test_booking_page_links_to_the_ai_page(): void
    {
        $this->get('/book')->assertOk()->assertSee('AIに相談する');
    }

    public function test_recommended_menu_is_shown_with_a_link_to_booking(): void
    {
        $this->fakeGemini(['menu_id' => $this->menu->id, 'reason' => '乾燥が気になる方に、うるおいを与えるメニューです。']);

        $this->followingRedirects()
            ->post('/ai', ['message' => '髪の乾燥が気になります'])
            ->assertOk()
            ->assertSee('うる艶トリートメント')
            ->assertSee('乾燥が気になる方に')
            ->assertSee('menu_id=' . $this->menu->id, false)
            ->assertSee('このメニューで予約に進む');

        Http::assertSent(fn (Request $r) => $r->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($r->url(), 'gemini-test:generateContent')
            && str_contains($r['contents'][0]['parts'][0]['text'], '髪の乾燥が気になります')
            && str_contains($r['systemInstruction']['parts'][0]['text'], 'うる艶トリートメント'));
    }

    public function test_unknown_menu_id_from_ai_is_treated_as_no_recommendation(): void
    {
        $this->fakeGemini(['menu_id' => 9999, 'reason' => '詳しくお伺いしたいです。']);

        $this->followingRedirects()
            ->post('/ai', ['message' => 'よく分かりません'])
            ->assertOk()
            ->assertSee('ご来店時に、直接ご相談ください。')
            ->assertDontSee('このメニューで予約に進む');
    }

    public function test_null_menu_id_is_no_recommendation(): void
    {
        $this->fakeGemini(['menu_id' => null, 'reason' => '一覧には合うものがありません。']);

        $this->followingRedirects()
            ->post('/ai', ['message' => '何か教えて'])
            ->assertSee('ご来店時に、直接ご相談ください。');
    }

    public function test_api_error_shows_a_message_and_keeps_the_input(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->from('/ai')->post('/ai', ['message' => '髪が広がります'])
            ->assertRedirect('/ai')
            ->assertSessionHasErrors('message')
            ->assertSessionHasInput('message', '髪が広がります');
    }

    public function test_unreadable_answer_is_an_error(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'JSONではありません']]]]]])]);

        $this->post('/ai', ['message' => '髪が広がります'])->assertSessionHasErrors('message');
    }

    public function test_missing_api_key_is_an_error_without_calling_the_api(): void
    {
        config(['ai.gemini.key' => null]);
        Http::fake();

        $this->post('/ai', ['message' => '髪が広がります'])->assertSessionHasErrors('message');

        Http::assertNothingSent();
    }

    public function test_message_is_required_and_limited_to_500_characters(): void
    {
        Http::fake();

        $this->post('/ai', ['message' => ''])->assertSessionHasErrors('message');
        $this->post('/ai', ['message' => str_repeat('あ', 501)])->assertSessionHasErrors('message');

        Http::assertNothingSent();
    }

    public function test_daily_limit_stops_further_requests(): void
    {
        config(['ai.daily_limit' => 1]);
        $this->fakeGemini(['menu_id' => $this->menu->id, 'reason' => '理由です。']);

        $this->post('/ai', ['message' => '1回目'])->assertSessionHasNoErrors();
        $this->post('/ai', ['message' => '2回目'])->assertSessionHasErrors('message');

        Http::assertSentCount(1);
    }
}
