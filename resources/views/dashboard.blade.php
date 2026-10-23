<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ダッシュボード</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-xs sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">明日の予約</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $tomorrowCount }}<span class="text-sm font-normal text-gray-500"> 件</span></div>
                </div>
                <div class="bg-white shadow-xs sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">今後7日間の予約（明日から）</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $weekCount }}<span class="text-sm font-normal text-gray-500"> 件</span></div>
                </div>
                <div class="bg-white shadow-xs sm:rounded-lg p-5">
                    <div class="text-xs text-gray-500">顧客の総数</div>
                    <div class="mt-1 text-2xl font-semibold">{{ $customerCount }}<span class="text-sm font-normal text-gray-500"> 人</span></div>
                </div>
            </div>

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
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
                                <td class="py-2">{{ $r->menu->name }}（{{ $r->minutes() }}分）</td>
                                <td class="py-2 text-right"><a href="{{ route('reservations.edit', $r) }}" class="text-indigo-600 underline">編集</a></td>
                            </tr>
                        @empty
                            <tr><td class="py-4 text-center text-gray-500">本日の予約はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-1">リマインド送信状況</h3>
                <p class="text-xs text-gray-500 mb-4">毎日18:00に、翌日の予約（確定のもの）へリマインドメールを自動送信します。</p>

                <div class="text-sm font-semibold text-gray-700 mb-2">明日（{{ today()->addDay()->format('n/j') }}）の予約</div>
                <table class="w-full text-sm text-left mb-6">
                    <tbody>
                        @forelse ($tomorrowReservations as $r)
                            <tr class="border-b">
                                <td class="py-2 whitespace-nowrap">{{ $r->scheduled_at->format('H:i') }}</td>
                                <td class="py-2">{{ $r->customer->name }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    @if ($r->reminder_sent_at)
                                        <span class="text-green-700">送信済み・{{ $r->reminder_channel === 'line' ? 'LINE' : 'メール' }}（{{ \Illuminate\Support\Carbon::parse($r->reminder_sent_at)->format('n/j H:i') }}）</span>
                                    @elseif (! $r->customer->line_user_id && ! $r->booked_email && ! $r->customer->email)
                                        <span class="text-red-600">LINE 未連携・メール未登録（送れません）</span>
                                    @elseif ($r->status !== 'confirmed')
                                        <span class="text-gray-500">対象外（確定前）</span>
                                    @else
                                        <span class="text-gray-600">未送信（18:00に自動送信）</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500">明日の予約はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="text-sm font-semibold text-gray-700 mb-2">最近送ったリマインド</div>
                <table class="w-full text-sm text-left">
                    <tbody>
                        @forelse ($recentReminders as $r)
                            <tr class="border-b">
                                <td class="py-2 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->reminder_sent_at)->format('n/j H:i') }}</td>
                                <td class="py-2">{{ $r->customer->name }} 様</td>
                                <td class="py-2 text-gray-500">{{ $r->scheduled_at->format('n/j H:i') }} の予約</td>
                                <td class="py-2 text-gray-500 text-right">{{ $r->reminder_channel === 'line' ? 'LINE' : 'メール' }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500">まだ送信履歴はありません。</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
