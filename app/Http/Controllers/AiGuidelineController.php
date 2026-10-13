<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// AI相談の「お店の判断基準」を、管理画面から書き換える
class AiGuidelineController extends Controller
{
    public const KEY = 'ai_guidelines';

    public function edit(): View
    {
        return view('ai-guidelines.edit', ['guidelines' => Setting::get(self::KEY, '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'guidelines' => ['nullable', 'string', 'max:3000'],
        ], [], [
            'guidelines' => '判断基準',
        ]);

        Setting::set(self::KEY, trim($data['guidelines'] ?? ''));

        return redirect()->route('ai-guidelines.edit')->with('status', 'AIの判断基準を保存しました。');
    }
}
