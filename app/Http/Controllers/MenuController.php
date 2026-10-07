<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(): View
    {
        $menus = Menu::orderBy('sort_order')->orderBy('id')->get();

        return view('menus.index', compact('menus'));
    }

    public function create(): View
    {
        return view('menus.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Menu::create($this->validated($request));

        return redirect()->route('menus.index')->with('status', 'メニューを登録しました。');
    }

    public function edit(Menu $menu): View
    {
        return view('menus.edit', compact('menu'));
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $menu->update($this->validated($request));

        return redirect()->route('menus.index')->with('status', 'メニューを更新しました。');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();

        return redirect()->route('menus.index')->with('status', 'メニューを削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:600'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], [
            'name' => 'メニュー名',
            'price' => '料金',
            'duration_minutes' => '所要時間',
            'description' => '説明',
            'sort_order' => '表示順',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
