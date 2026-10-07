<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">明日の予約</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $tomorrowCount }}<span class="text-sm font-normal text-gray-500"> 件</span></div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">今後7日間の予約（明日から）</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $weekCount }}<span class="text-sm font-normal text-gray-500"> 件</span></div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">顧客の総数</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $customerCount }}<span class="text-sm font-normal text-gray-500"> 人</span></div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-800">本日の予約（{{ today()->format('Y/m/d') }}）</h3>
                    <a href="{{ route('reservations.create') }}" class="text-sm text-indigo-600 underline">予約を登録</a>
                </div>
                <table class="w-full text-sm text-left">
                    <tbody>
                        @forelse ($todayReservations as $r)
                            <tr class="border-b">
                                <td class="py-2 whitespace-nowrap">{{ $r->scheduled_at->format('H:i') }}</td>
                                <td class="py-2"><a href="{{ route('customers.show', $r->customer) }}" class="text-indigo-600 underline">{{ $r->customer->name }}</a></td>
                                <td class="py-2">{{ $r->menu->name }}（{{ $r->menu->duration_minutes }}分）</td>
                                <td class="py-2 text-right"><a href="{{ route('reservations.edit', $r) }}" class="text-indigo-600 underline">編集</a></td>
                            </tr>
                        @empty
                            <tr><td class="py-4 text-center text-gray-500">本日の予約はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-2">リマインド送信状況</h3>
                <p class="text-sm text-gray-500">リマインドメール機能の実装後に表示されます（Week23予定）。</p>
            </div>
        </div>
    </div>
</x-app-layout>
