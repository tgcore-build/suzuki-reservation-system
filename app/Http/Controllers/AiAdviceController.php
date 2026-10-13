<?php

namespace App\Http\Controllers;

use App\Models\AiSession;
use App\Models\Menu;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MenuAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AiAdviceController extends Controller
{
    private const SESSION_KEY = 'ai_session_id';

    public function index(Request $request): View
    {
        $aiSession = $this->currentSession($request);
        $resultMenu = $aiSession?->result_menu
            ? Menu::where('is_active', true)->where('name', $aiSession->result_menu)->first()
            : null;

        return view('ai.index', [
            'aiSession' => $aiSession,
            'messages' => $aiSession?->messages ?? collect(),
            'resultMenu' => $resultMenu,
        ]);
    }

    public function store(Request $request, MenuAdvisor $advisor): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ], [], [
            'message' => 'ご相談内容',
        ]);

        $menus = Menu::where('is_active', true)->orderBy('sort_order')->get();
        if ($menus->isEmpty()) {
            return back()->withInput()->withErrors(['message' => '現在ご案内できるメニューがありません。']);
        }

        // 1日の問い合わせ回数の上限（費用が増えすぎないように）
        $limitKey = 'ai-advice:' . today()->format('Y-m-d');
        $count = (int) Cache::get($limitKey, 0);
        if ($count >= (int) config('ai.daily_limit')) {
            return back()->withInput()->withErrors(['message' => '本日のAI相談の受付は終了しました。メニュー選択からご予約ください。']);
        }
        Cache::put($limitKey, $count + 1, now()->endOfDay());

        // 進行中の会話がなければ、新しく始める（終わった会話の続きはしない）
        $aiSession = $this->currentSession($request);
        $history = $aiSession && ! $aiSession->isCompleted()
            ? $aiSession->messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->all()
            : [];
        $history[] = ['role' => 'user', 'content' => $data['message']];

        $userTurns = count(array_filter($history, fn ($m) => $m['role'] === 'user'));
        $phase = match (true) {
            $userTurns >= (int) config('ai.max_turns') => 'decide', // 最後の回は必ず提案
            $userTurns === 1 => 'ask',                               // 1回目は必ず質問を返す
            default => 'normal',
        };

        try {
            $result = $advisor->chat($history, $menus, $phase);
        } catch (AiUnavailableException $e) {
            report($e);

            return back()->withInput()->withErrors([
                'message' => 'AIの応答を取得できませんでした。もう一度お試しいただくか、ご来店時に直接ご相談ください。',
            ]);
        }

        // AIの応答が取れたときだけ、会話を保存する
        DB::transaction(function () use (&$aiSession, $data, $result) {
            if (! $aiSession || $aiSession->isCompleted()) {
                $aiSession = AiSession::create(['status' => 'in_progress']);
            }
            $aiSession->messages()->create(['role' => 'user', 'content' => $data['message']]);
            $aiSession->messages()->create(['role' => 'ai', 'content' => $result['reply']]);

            if ($result['is_final']) {
                $aiSession->update(['status' => 'completed', 'result_menu' => $result['menu']?->name]);
            }
        });

        $request->session()->put(self::SESSION_KEY, $aiSession->id);

        return redirect()->route('ai.index');
    }

    // 「最初からやり直す」
    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('ai.index');
    }

    private function currentSession(Request $request): ?AiSession
    {
        $id = $request->session()->get(self::SESSION_KEY);

        return $id ? AiSession::with('messages')->find($id) : null;
    }
}
