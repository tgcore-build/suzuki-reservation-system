<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI提案結果</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('ai-sessions.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
                    <div>
                        <label for="status" class="block text-xs text-gray-500">状態</label>
                        <select id="status" name="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">すべて</option>
                            @foreach (\App\Models\AiSession::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="booked" class="block text-xs text-gray-500">予約</label>
                        <select id="booked" name="booked" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">すべて</option>
                            <option value="yes" @selected(request('booked') === 'yes')>済</option>
                            <option value="no" @selected(request('booked') === 'no')>未</option>
                        </select>
                    </div>
                    <x-secondary-button type="submit">絞り込む</x-secondary-button>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-gray-500 border-b">
                            <tr>
                                <th class="py-2">日時</th>
                                <th class="py-2">提案メニュー</th>
                                <th class="py-2">状態</th>
                                <th class="py-2">相談回数</th>
                                <th class="py-2">予約</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sessions as $s)
                                <tr class="border-b">
                                    <td class="py-2 whitespace-nowrap">{{ $s->created_at->format('Y/m/d H:i') }}</td>
                                    <td class="py-2">{{ $s->result_menu ?? '—' }}</td>
                                    <td class="py-2">{{ \App\Models\AiSession::STATUSES[$s->status] ?? $s->status }}</td>
                                    <td class="py-2">{{ $s->messages_count }}回</td>
                                    <td class="py-2">
                                        @if ($s->customer)
                                            済（<a href="{{ route('customers.show', $s->customer) }}" class="text-indigo-600 underline">{{ $s->customer->name }}</a>）
                                        @else
                                            未
                                        @endif
                                    </td>
                                    <td class="py-2 text-right"><a href="{{ route('ai-sessions.show', $s) }}" class="text-indigo-600 underline">会話を見る</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-gray-500">AI相談の記録はありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $sessions->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
