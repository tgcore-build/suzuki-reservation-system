<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\MenuAdvisor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AiAdviceController extends Controller
{
    public function index(): View
    {
        return view('ai.index');
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

        // 1日の利用回数の上限（費用が増えすぎないように）
        $limitKey = 'ai-advice:' . today()->format('Y-m-d');
        $count = (int) Cache::get($limitKey, 0);
        if ($count >= (int) config('ai.daily_limit')) {
            return back()->withInput()->withErrors(['message' => '本日のAI相談の受付は終了しました。メニュー選択からご予約ください。']);
        }
        Cache::put($limitKey, $count + 1, now()->endOfDay());

        try {
            $result = $advisor->advise($data['message'], $menus);
        } catch (AiUnavailableException $e) {
            report($e);

            return back()->withInput()->withErrors([
                'message' => 'AIの応答を取得できませんでした。もう一度お試しいただくか、ご来店時に直接ご相談ください。',
            ]);
        }

        return redirect()->route('ai.index')->with('advice', [
            'message' => $data['message'],
            'menu_id' => $result['menu']?->id,
            'menu_name' => $result['menu']?->name,
            'reason' => $result['reason'],
        ]);
    }
}
