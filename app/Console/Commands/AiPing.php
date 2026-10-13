<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MenuAdvisor;
use Illuminate\Console\Command;

class AiPing extends Command
{
    protected $signature = 'ai:ping {message=髪の乾燥が気になります}';

    protected $description = 'AI提案の接続テスト（APIキー・モデル・応答の形を確認する）';

    public function handle(MenuAdvisor $advisor): int
    {
        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        $this->info('メニュー ' . $menus->count() . '件で相談します: ' . $this->argument('message'));

        try {
            $result = $advisor->chat([['role' => 'user', 'content' => $this->argument('message')]], $menus, 'normal');
        } catch (AiUnavailableException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line('AIの返事: ' . $result['reply']);
        $this->line('会話の終わり: ' . ($result['is_final'] ? 'はい' : 'いいえ（続けて質問）'));
        $this->line('おすすめ: ' . ($result['menu']?->name ?? '（なし）'));

        return self::SUCCESS;
    }
}
