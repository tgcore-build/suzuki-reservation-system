<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGuidelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_requires_login(): void
    {
        $this->get('/ai-guidelines')->assertRedirect('/login');
        $this->put('/ai-guidelines', ['guidelines' => 'x'])->assertRedirect('/login');
    }

    public function test_admin_can_save_guidelines(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/ai-guidelines', ['guidelines' => '頭皮のかゆみ → ヘッドスパ'])
            ->assertRedirect('/ai-guidelines');

        $this->assertSame('頭皮のかゆみ → ヘッドスパ', Setting::get('ai_guidelines'));
        $this->actingAs($user)->get('/ai-guidelines')->assertOk()->assertSee('頭皮のかゆみ → ヘッドスパ');
    }

    public function test_menu_descriptions_and_guidelines_are_sent_to_the_ai(): void
    {
        config(['ai.gemini.key' => 'test-key', 'ai.daily_limit' => 100, 'ai.max_turns' => 4]);
        Menu::create([
            'name' => '炭酸スパ', 'price' => 4400, 'duration_minutes' => 30, 'is_active' => true, 'sort_order' => 1,
            'description' => 'かゆみ・フケ・におい・頭皮の乾燥が気になる方向け',
        ]);
        Setting::set('ai_guidelines', '頭皮が硬い → 炭酸スパ');

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => 'いつ頃からですか？', 'is_final' => false, 'menu_id' => null], JSON_UNESCAPED_UNICODE)]]]]],
        ])]);

        $this->post('/ai', ['message' => '頭皮がかゆい'])->assertSessionHasNoErrors();

        Http::assertSent(function (Request $r) {
            $text = $r['systemInstruction']['parts'][0]['text'];

            return str_contains($text, '説明：かゆみ・フケ・におい・頭皮の乾燥が気になる方向け')
                && str_contains($text, '【お店の判断基準】')
                && str_contains($text, '頭皮が硬い → 炭酸スパ');
        });
    }
}
