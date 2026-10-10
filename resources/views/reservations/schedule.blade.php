<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">予約表</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('reservations.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">一覧で見る</a>
                <a href="{{ route('reservations.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">予約を追加</a>
            </div>
        </div>
    </x-slot>

    @php
        $weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $gridHeight = ($endHour - $startHour) * $rowHeight;
        $pxPerMin = $rowHeight / 60;
        $linkBase = fn (string $s, int $w) => route('schedule.index', ['start' => $s, 'weeks' => $w]);
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <a href="{{ $linkBase($prevStart, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md bg-white hover:bg-gray-50">← 前へ</a>
                <a href="{{ $linkBase($thisWeek, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md bg-white hover:bg-gray-50">今週</a>
                <a href="{{ $linkBase($nextStart, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md bg-white hover:bg-gray-50">次へ →</a>
                <span class="ml-2 font-semibold">{{ $start->format('Y/n/j') }} 〜 {{ $end->copy()->subDay()->format('Y/n/j') }}</span>

                <span class="ml-auto flex items-center gap-1">
                    <a href="{{ $linkBase($start->format('Y-m-d'), 1) }}" class="px-3 py-1 border rounded-md {{ $weeks === 1 ? 'bg-gray-800 text-white border-gray-800' : 'bg-white border-gray-300 hover:bg-gray-50' }}">1週間</a>
                    <a href="{{ $linkBase($start->format('Y-m-d'), 2) }}" class="px-3 py-1 border rounded-md {{ $weeks === 2 ? 'bg-gray-800 text-white border-gray-800' : 'bg-white border-gray-300 hover:bg-gray-50' }}">2週間</a>
                </span>
            </div>

            @foreach ($weekGrids as $days)
                <div class="bg-white shadow-sm sm:rounded-lg p-3 overflow-x-auto">
                    <div style="min-width: 760px;">
                        {{-- 見出し（日付） --}}
                        <div style="display: grid; grid-template-columns: 3rem repeat(7, minmax(0, 1fr));" class="text-xs">
                            <div></div>
                            @foreach ($days as $day)
                                @php
                                    $bh = $hours[$day->dayOfWeek] ?? null;
                                    $closed = $bh && ! $bh->is_open;
                                    $isToday = $day->isToday();
                                @endphp
                                <div class="py-2 text-center border-l {{ $closed ? 'bg-gray-100 text-gray-400' : '' }} {{ $isToday ? 'bg-yellow-50 font-semibold' : '' }}">
                                    {{ $day->format('n/j') }}（{{ $weekdayNames[$day->dayOfWeek] }}）
                                    @if ($closed)<span class="block">休業</span>@endif
                                </div>
                            @endforeach
                        </div>

                        {{-- 本体（時間の軸 + 7日分） --}}
                        <div style="display: grid; grid-template-columns: 3rem repeat(7, minmax(0, 1fr));" class="text-xs border-t">
                            <div>
                                @for ($h = $startHour; $h < $endHour; $h++)
                                    <div style="height: {{ $rowHeight }}px;" class="pr-1 text-right text-gray-400">{{ $h }}:00</div>
                                @endfor
                            </div>

                            @foreach ($days as $day)
                                @php
                                    $bh = $hours[$day->dayOfWeek] ?? null;
                                    $closed = $bh && ! $bh->is_open;
                                    $dayReservations = $byDay[$day->format('Y-m-d')] ?? collect();
                                    $bg = $closed ? '#f3f4f6' : ($day->isToday() ? '#fefce8' : '#ffffff');
                                @endphp
                                <div class="border-l" style="position: relative; height: {{ $gridHeight }}px; background-color: {{ $bg }}; background-image: repeating-linear-gradient(to bottom, #e5e7eb 0, #e5e7eb 1px, transparent 1px, transparent {{ $rowHeight }}px);">
                                    @foreach ($dayReservations as $r)
                                        @php
                                            $minutes = $r->menu?->duration_minutes ?? 60;
                                            $top = ($r->scheduled_at->hour * 60 + $r->scheduled_at->minute - $startHour * 60) * $pxPerMin;
                                            $height = max($minutes * $pxPerMin - 2, 18);
                                            $colors = $r->status === 'completed'
                                                ? 'background: #dcfce7; border-color: #86efac;'
                                                : 'background: #e0e7ff; border-color: #a5b4fc;';
                                        @endphp
                                        <a href="{{ route('reservations.edit', $r) }}"
                                           title="{{ $r->scheduled_at->format('H:i') }} {{ $r->customer?->name }} / {{ $r->menu?->name }}"
                                           class="rounded border px-1 leading-tight text-gray-800 hover:opacity-80"
                                           style="position: absolute; left: 2px; right: 2px; top: {{ $top }}px; height: {{ $height }}px; overflow: hidden; {{ $colors }}">
                                            <span class="font-semibold">{{ $r->scheduled_at->format('H:i') }}</span>
                                            {{ $r->customer?->name }}
                                            <span class="block text-gray-600">{{ $r->menu?->name }}@if ($r->source === 'online')（Web）@endif</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            <p class="text-xs text-gray-500">予約をクリックすると、編集画面が開きます。キャンセル済みの予約は表示されません。</p>
        </div>
    </div>
</x-app-layout>
