<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">取り込み内容の確認</h2>
    </x-slot>

    @php
        $newCount = $rows->where('status', 'new')->count();
        $dupCount = $rows->where('status', 'duplicate')->count();
        $errCount = $rows->where('status', 'error')->count();
    @endphp

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 text-sm">
                <p class="mb-4">
                    登録する：<strong>{{ $newCount }}件</strong>　／
                    重複でスキップ：{{ $dupCount }}件　／
                    エラーでスキップ：{{ $errCount }}件
                </p>

                <div class="flex items-center gap-4">
                    @if ($newCount > 0)
                        <form method="POST" action="{{ route('customer-import.confirm') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">{{ $newCount }}件を登録する</button>
                        </form>
                    @endif
                    <a href="{{ route('customer-import.create') }}" class="text-gray-600 underline">ファイルを選び直す</a>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2">行</th>
                            <th class="py-2">氏名</th>
                            <th class="py-2">電話番号</th>
                            <th class="py-2">メール</th>
                            <th class="py-2">生年月日</th>
                            <th class="py-2">結果</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $color = match ($row['status']) {
                                    'new' => 'text-green-700',
                                    'duplicate' => 'text-gray-500',
                                    default => 'text-red-700',
                                };
                            @endphp
                            <tr class="border-t">
                                <td class="py-2">{{ $row['line'] }}</td>
                                <td class="py-2">{{ $row['data']['name'] }}</td>
                                <td class="py-2">{{ $row['data']['phone'] ?? '—' }}</td>
                                <td class="py-2">{{ $row['data']['email'] ?? '—' }}</td>
                                <td class="py-2">{{ $row['data']['birthday'] ?? '—' }}</td>
                                <td class="py-2 {{ $color }}">
                                    {{ $row['status'] === 'new' ? '登録します' : $row['reason'] }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
