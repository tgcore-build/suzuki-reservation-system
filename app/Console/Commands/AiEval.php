<?php

namespace App\Console\Commands;

use App\Models\Menu;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MenuAdvisor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

// 点検用の相談リストを、まとめてAIに送り、提案を一覧で表示する
class AiEval extends Command
{
    protected $signature = 'ai:eval {--file= : 相談リストのファイル（省略時は database/ai-eval-cases.txt）} {--wait=1 : 1件ごとに待つ秒数}';

    protected $description = 'AI相談の点検（相談リストの全件で、どのメニューを提案するかを一覧にする）';

    public function handle(MenuAdvisor $advisor): int
    {
        $file = $this->option('file') ?: database_path('ai-eval-cases.txt');
        if (! is_file($file)) {
            $this->error("相談リストが見つかりません: {$file}");

            return self::FAILURE;
        }

        $cases = collect(file($file, FILE_IGNORE_NEW_LINES))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '' && ! str_starts_with($line, '#'))
            ->values();

        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        $this->info("メニュー {$menus->count()}件 / 相談 {$cases->count()}件 / モデル " . config('ai.gemini.model'));

        $rows = [];
        foreach ($cases as $i => $case) {
            if ($i > 0) {
                sleep((int) $this->option('wait')); // 無料枠の回数制限に引っかからないように
            }

            $start = microtime(true);
            try {
                // 点検では、1回で提案まで出させる（会話の最後の回と同じ扱い）
                $result = $advisor->chat([['role' => 'user', 'content' => $case]], $menus, 'decide');
                $menu = $result['menu']?->name ?? '（なし）';
                $reply = $result['reply'];
            } catch (AiUnavailableException $e) {
                $menu = 'エラー';
                $reply = Str::limit($e->getMessage(), 80);
            }

            $rows[] = [$i + 1, $case, $menu, round(microtime(true) - $start, 1) . '秒', Str::limit($reply, 60)];
            $this->line(($i + 1) . ". {$case} → {$menu}");
        }

        $this->newLine();
        $this->table(['#', '相談', 'おすすめ', '時間', 'AIの返事（先頭）'], $rows);

        return self::SUCCESS;
    }
}
