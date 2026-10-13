<?php

namespace Tests\Feature;

use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiEvalTest extends TestCase
{
    use RefreshDatabase;

    public function test_eval_runs_every_case_and_lists_the_recommendations(): void
    {
        config(['ai.gemini.key' => 'test-key']);
        $menu = Menu::create(['name' => '炭酸スパ', 'price' => 4400, 'duration_minutes' => 30, 'is_active' => true, 'sort_order' => 1]);

        $file = tempnam(sys_get_temp_dir(), 'cases');
        file_put_contents($file, "# コメント\n頭皮がかゆい\n\nフケが気になる\n");

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => '炭酸スパがおすすめです。', 'is_final' => true, 'menu_id' => $menu->id], JSON_UNESCAPED_UNICODE)]]]]],
        ])]);

        $this->artisan('ai:eval', ['--file' => $file, '--wait' => 0])
            ->expectsOutputToContain('相談 2件')
            ->expectsOutputToContain('1. 頭皮がかゆい → 炭酸スパ')
            ->expectsOutputToContain('2. フケが気になる → 炭酸スパ')
            ->assertSuccessful();

        Http::assertSentCount(2);
        unlink($file);
    }
}
