<?php

namespace Tests\Feature;

use App\Models\AiMessage;
use App\Models\AiSession;
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

        config([
            'ai.gemini.key' => 'test-key',
            'ai.gemini.model' => 'gemini-test',
            'ai.daily_limit' => 100,
            'ai.max_turns' => 4,
        ]);

        $this->menu = Menu::create([
            'name' => 'うる艶トリートメント',
            'price' => 5500,
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function answer(array $data): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => json_encode($data, JSON_UNESCAPED_UNICODE)]]],
            ]],
        ];
    }

    private function fakeAnswers(array ...$answers): void
    {
        $sequence = Http::sequence();
        foreach ($answers as $a) {
            $sequence->push($this->answer($a));
        }
        Http::fake(['generativelanguage.googleapis.com/*' => $sequence]);
    }

    public function test_page_shows_the_first_question_and_the_form(): void
    {
        $this->get('/ai')->assertOk()
            ->assertSee('どんなお悩みですか？')
            ->assertSee('個人が特定できる情報');
    }

    public function test_booking_page_links_to_the_ai_page(): void
    {
        $this->get('/book')->assertOk()->assertSee('AIに相談する');
    }

    public function test_conversation_continues_and_ends_with_a_recommendation(): void
    {
        $this->fakeAnswers(
            ['reply' => 'パサつきと広がり、どちらが気になりますか？', 'is_final' => false, 'menu_id' => null],
            ['reply' => 'うるおいを与えるトリートメントがおすすめです。', 'is_final' => true, 'menu_id' => $this->menu->id],
        );

        // 1回目：AIが質問を返す
        $this->followingRedirects()->post('/ai', ['message' => '髪の乾燥が気になります'])
            ->assertOk()
            ->assertSee('髪の乾燥が気になります')
            ->assertSee('どちらが気になりますか？')
            ->assertSee('送信する')
            ->assertDontSee('このメニューで予約に進む');

        $session = AiSession::first();
        $this->assertSame('in_progress', $session->status);
        $this->assertSame(2, $session->messages()->count());

        // 2回目：これまでの会話を送って、提案で終わる
        $this->followingRedirects()->post('/ai', ['message' => 'パサつきです'])
            ->assertOk()
            ->assertSee('うる艶トリートメント')
            ->assertSee('menu_id=' . $this->menu->id, false)
            ->assertSee('このメニューで予約に進む')
            ->assertDontSee('送信する');

        $session->refresh();
        $this->assertSame('completed', $session->status);
        $this->assertSame('うる艶トリートメント', $session->result_menu);
        $this->assertSame(4, $session->messages()->count());
        $this->assertSame(1, AiSession::count());

        Http::assertSent(fn (Request $r) => count($r['contents']) === 3
            && $r['contents'][0]['role'] === 'user'
            && $r['contents'][1]['role'] === 'model'
            && $r['contents'][2]['parts'][0]['text'] === 'パサつきです'
            && $r->hasHeader('x-goog-api-key', 'test-key')
            && str_contains($r->url(), 'gemini-test:generateContent'));
    }

    public function test_last_turn_forces_a_decision(): void
    {
        config(['ai.max_turns' => 2]);
        $this->fakeAnswers(
            ['reply' => 'ほかに気になる点はありますか？', 'is_final' => false, 'menu_id' => null],
            // 最後の回なのにAIが質問を続けても、会話は終わりとして扱う
            ['reply' => 'まだ質問があります', 'is_final' => false, 'menu_id' => $this->menu->id],
        );

        $this->post('/ai', ['message' => '乾燥します']);
        $this->post('/ai', ['message' => '特にないです']);

        $this->assertSame('completed', AiSession::first()->status);
        $this->assertSame('うる艶トリートメント', AiSession::first()->result_menu);

        Http::assertSent(fn (Request $r) => count($r['contents']) === 3
            && str_contains($r['systemInstruction']['parts'][0]['text'], '今回で必ず会話を終える'));
    }

    public function test_unknown_menu_id_from_ai_is_treated_as_no_recommendation(): void
    {
        config(['ai.max_turns' => 1]); // 1回目で提案して終える設定
        $this->fakeAnswers(['reply' => '詳しくお伺いしたいです。', 'is_final' => true, 'menu_id' => 9999]);

        $this->followingRedirects()->post('/ai', ['message' => 'よく分かりません'])
            ->assertOk()
            ->assertSee('ご来店時に、直接ご相談ください。')
            ->assertDontSee('このメニューで予約に進む');

        $this->assertNull(AiSession::first()->result_menu);
    }

    public function test_reset_starts_a_new_conversation(): void
    {
        $this->fakeAnswers(
            ['reply' => 'どちらが気になりますか？', 'is_final' => false, 'menu_id' => null],
            ['reply' => '別の質問です', 'is_final' => false, 'menu_id' => null],
        );

        $this->post('/ai', ['message' => '1回目']);
        $this->post('/ai/reset')->assertRedirect('/ai');
        $this->get('/ai')->assertSee('どんなお悩みですか？')->assertDontSee('1回目');

        $this->post('/ai', ['message' => '新しい相談']);

        $this->assertSame(2, AiSession::count());
        Http::assertSent(fn (Request $r) => count($r['contents']) === 1 && $r['contents'][0]['parts'][0]['text'] === '新しい相談');
    }

    public function test_first_turn_always_continues_even_if_ai_tries_to_finish(): void
    {
        $this->fakeAnswers(['reply' => 'ご来店時にご相談ください。', 'is_final' => true, 'menu_id' => $this->menu->id]);

        $this->followingRedirects()->post('/ai', ['message' => '頭皮がかゆい'])
            ->assertOk()
            ->assertSee('送信する')
            ->assertDontSee('このメニューで予約に進む');

        $this->assertSame('in_progress', AiSession::first()->status);
        Http::assertSent(fn (Request $r) => str_contains($r['systemInstruction']['parts'][0]['text'], '提案せずに')
            && str_contains($r['systemInstruction']['parts'][0]['text'], '皮膚科'));
    }

    public function test_reply_ending_with_a_question_does_not_finish_the_conversation(): void
    {
        $this->fakeAnswers(
            ['reply' => 'いつ頃から気になりますか？', 'is_final' => false, 'menu_id' => null],
            ['reply' => 'ご来店時にご相談いただけますでしょうか？', 'is_final' => true, 'menu_id' => null],
        );

        $this->post('/ai', ['message' => '頭皮がかゆい']);
        $this->followingRedirects()->post('/ai', ['message' => '先月からです'])
            ->assertSee('送信する');

        $this->assertSame('in_progress', AiSession::first()->status);
    }

    public function test_api_error_shows_a_message_and_saves_nothing(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'boom'], 500)]);

        $this->from('/ai')->post('/ai', ['message' => '髪が広がります'])
            ->assertRedirect('/ai')
            ->assertSessionHasErrors('message')
            ->assertSessionHasInput('message', '髪が広がります');

        $this->assertSame(0, AiSession::count());
        $this->assertSame(0, AiMessage::count());
    }

    public function test_unreadable_answer_is_an_error(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'JSONではありません']]]]]])]);

        $this->post('/ai', ['message' => '髪が広がります'])->assertSessionHasErrors('message');
        $this->assertSame(0, AiMessage::count());
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
        $this->fakeAnswers(['reply' => '質問です', 'is_final' => false, 'menu_id' => null]);

        $this->post('/ai', ['message' => '1回目'])->assertSessionHasNoErrors();
        $this->post('/ai', ['message' => '2回目'])->assertSessionHasErrors('message');

        Http::assertSentCount(1);
    }
}
