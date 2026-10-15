<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Services\Ai\MenuAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_busy_response_is_retried_once(): void
    {
        config(['ai.gemini.key' => 'test-key']);
        $menu = Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => 'busy'], 503)
            ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode(['reply' => 'カットがおすすめです。', 'is_final' => true, 'menu_id' => $menu->id], JSON_UNESCAPED_UNICODE)]]]]]]),
        ]);

        $result = app(MenuAdvisor::class)->chat([['role' => 'user', 'content' => '短くしたい']], Menu::all(), 'decide');

        $this->assertSame('カット', $result['menu']->name);
        Http::assertSentCount(2);
    }

    public function test_bad_request_is_not_retried(): void
    {
        config(['ai.gemini.key' => 'test-key']);
        Menu::create(['name' => 'カット', 'price' => 6600, 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => 'bad'], 400)]);

        try {
            app(MenuAdvisor::class)->chat([['role' => 'user', 'content' => '短くしたい']], Menu::all(), 'decide');
            $this->fail('例外が出るはず');
        } catch (\App\Services\Ai\AiUnavailableException $e) {
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
        }

        Http::assertSentCount(1);
    }
}
