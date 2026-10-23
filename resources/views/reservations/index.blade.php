<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約管理</h2>
            <a href="{{ route('reservations.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">新規登録</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <form method="GET" action="{{ route('reservations.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
                    <div>
                        <label for="date" class="block text-xs text-gray-500">日付で絞り込み</label>
                        <input id="date" name="date" type="date" value="{{ request('date') }}" class="border-gray-300 rounded-md shadow-xs text-sm">
                    </div>
                    <div>
                        <label for="status" class="block text-xs text-gray-500">状態</label>
                        <select id="status" name="status" class="border-gray-300 rounded-md shadow-xs text-sm">
                            <option value="">すべて</option>
                            @foreach (\App\Models\Reservation::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit">絞り込む</x-secondary-button>
                    <div class="text-sm ms-auto">
                        @if (request()->boolean('past'))
                            <a href="{{ route('reservations.index') }}" class="text-indigo-600 underline">今日以降だけ表示</a>
                        @else
                            <a href="{{ route('reservations.index', ['past' => 1]) }}" class="text-indigo-600 underline">過去の予約も表示</a>
                        @endif
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-gray-500 border-b">
                            <tr>
                                <th class="py-2">日時</th>
                                <th class="py-2">顧客</th>
                                <th class="py-2">メニュー</th>
                                <th class="py-2">状態</th>
                                <th class="py-2">経路</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $weekdays = ['日', '月', '火', '水', '木', '金', '土']; @endphp
                            @forelse ($reservations as $r)
                                <tr class="border-b {{ $r->status === 'cancelled' ? 'text-gray-400' : '' }}">
                                    <td class="py-2 whitespace-nowrap">{{ $r->scheduled_at->format('Y/m/d') }}（{{ $weekdays[$r->scheduled_at->dayOfWeek] }}） {{ $r->scheduled_at->format('H:i') }}</td>
                                    <td class="py-2"><a href="{{ route('customers.show', $r->customer) }}" class="text-indigo-600 underline">{{ $r->customer->name }}</a>
                                        @if ($r->needs_review)
                                            <span class="ml-1 px-1.5 py-0.5 text-xs rounded-sm bg-amber-100 text-amber-800" title="電話番号は一致しましたが、入力された名前またはメールが登録と違います">要確認</span>
                                            <div class="text-xs text-gray-500">入力：{{ $r->booked_name }} / {{ $r->booked_email }}</div>
                                        @endif
                                        @if ($r->counseled_at)
                                            <div class="mt-1 text-xs text-gray-600">
                                                <span class="px-1.5 py-0.5 rounded-sm bg-indigo-50 text-indigo-700">事前カウンセリング</span>
                                                {{ implode('、', $r->concernLabels()) }}@if ($r->concern_note)　{{ \Illuminate\Support\Str::limit($r->concern_note, 40) }}@endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2">{{ $r->menu->name }}（{{ $r->minutes() }}分）</td>
                                    <td class="py-2">{{ \App\Models\Reservation::STATUSES[$r->status] }}</td>
                                    <td class="py-2">{{ $r->source === 'online' ? 'オンライン' : '管理者' }}</td>
                                    <td class="py-2 text-right whitespace-nowrap">
                                        <a href="{{ route('reservations.edit', $r) }}" class="text-indigo-600 underline">編集</a>
                                        @if ($r->status !== 'cancelled')
                                            <form method="POST" action="{{ route('reservations.destroy', $r) }}" class="inline" onsubmit="return confirm('この予約をキャンセルします。よろしいですか？');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="ms-3 text-red-600 underline">キャンセル</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-gray-500">該当する予約がありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $reservations->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
