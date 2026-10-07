<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">メニュー管理</h2>
            <a href="{{ route('menus.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">新規登録</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6 overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500 border-b">
                        <tr>
                            <th class="py-2">表示順</th>
                            <th class="py-2">メニュー名</th>
                            <th class="py-2">料金</th>
                            <th class="py-2">所要時間</th>
                            <th class="py-2">公開</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($menus as $menu)
                            <tr class="border-b">
                                <td class="py-2">{{ $menu->sort_order }}</td>
                                <td class="py-2">{{ $menu->name }}</td>
                                <td class="py-2">{{ number_format($menu->price) }}円</td>
                                <td class="py-2">{{ $menu->duration_minutes }}分</td>
                                <td class="py-2">{{ $menu->is_active ? '公開' : '非公開' }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('menus.edit', $menu) }}" class="text-indigo-600 underline">編集</a>
                                    <form method="POST" action="{{ route('menus.destroy', $menu) }}" class="inline" onsubmit="return confirm('「{{ $menu->name }}」を削除します。よろしいですか？');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="ms-3 text-red-600 underline">削除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500">メニューがまだ登録されていません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
