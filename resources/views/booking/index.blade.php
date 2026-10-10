@extends('booking.layout')

@section('content')
    @php
        $weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $link = fn (string $startDate, int $w) => route('booking.index', ['menu_id' => $menu->id, 'start' => $startDate, 'weeks' => $w]);
    @endphp

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ① メニューを選ぶ --}}
    <form method="GET" action="{{ route('booking.index') }}" class="bg-white shadow-sm rounded-lg p-5 space-y-4">
        <input type="hidden" name="weeks" value="{{ $weeks }}">
        <div>
            <label class="block text-sm text-gray-700 mb-1">① メニューを選んでください</label>
            <select name="menu_id" required class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                <option value="">選択してください</option>
                @foreach ($menus as $m)
                    <option value="{{ $m->id }}" @selected($menu && $menu->id === $m->id)>
                        {{ $m->name }}（約{{ $m->duration_minutes }}分・¥{{ number_format($m->price) }}）
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">空き状況を見る</button>
    </form>

    @if ($menu)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-4">
            <h2 class="font-semibold mb-3">② ご希望の日時を選んでください　<span class="text-sm font-normal text-gray-500">{{ $menu->name }}</span></h2>

            {{-- 前へ・次へ / 1週間・2週間 --}}
            <div class="flex flex-wrap items-center gap-2 text-sm mb-4">
                @if ($prevStart)
                    <a href="{{ $link($prevStart, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">← 前へ</a>
                @endif
                <a href="{{ $link($thisWeek, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">今週</a>
                @if ($nextStart)
                    <a href="{{ $link($nextStart, $weeks) }}" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-50">次へ →</a>
                @endif

                <span class="ml-auto flex items-center gap-1">
                    <a href="{{ $link($start->format('Y-m-d'), 1) }}" class="px-3 py-1 border rounded-md {{ $weeks === 1 ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 hover:bg-gray-50' }}">1週間</a>
                    <a href="{{ $link($start->format('Y-m-d'), 2) }}" class="px-3 py-1 border rounded-md {{ $weeks === 2 ? 'bg-gray-800 text-white border-gray-800' : 'border-gray-300 hover:bg-gray-50' }}">2週間</a>
                </span>
            </div>

            @if (count($times) === 0)
                <p class="text-sm text-gray-600 mb-4">この期間に予約できる時間がありません。「次へ」で、ほかの週をご覧ください。</p>
            @endif

            <form method="POST" action="{{ route('booking.store') }}" class="space-y-6" onsubmit="this.querySelector('button[type=submit]').disabled = true">
                @csrf
                <input type="hidden" name="menu_id" value="{{ $menu->id }}">

                @if (count($times) > 0)
                    <p class="text-xs text-gray-500">○ をタップして、ご希望の日時を選んでください（× は予約できません）。</p>

                    @foreach ($weekGrids as $days)
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-center border-collapse" style="min-width: 420px;">
                                <thead>
                                    <tr>
                                        <th style="width: 3rem;"></th>
                                        @foreach ($days as $day)
                                            @php $isClosed = $closed[$day->format('Y-m-d')] ?? false; @endphp
                                            <th class="py-1 font-semibold {{ $day->isToday() ? 'bg-yellow-50' : '' }} {{ $isClosed ? 'text-gray-400' : '' }}">
                                                {{ $day->format('n/j') }}<br>{{ $weekdayNames[$day->dayOfWeek] }}
                                                @if ($isClosed)<br><span class="font-normal">休</span>@endif
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($times as $time)
                                        <tr class="border-t">
                                            <th class="py-1 font-normal text-gray-500">{{ $time }}</th>
                                            @foreach ($days as $day)
                                                @php
                                                    $key = $day->format('Y-m-d');
                                                    $isOpen = in_array($time, $slotMap[$key] ?? [], true);
                                                @endphp
                                                <td class="p-0.5">
                                                    @if ($isOpen)
                                                        <label>
                                                            <input type="radio" name="slot" value="{{ $key }}|{{ $time }}" class="peer sr-only" required>
                                                            <span class="block py-1.5 rounded border border-gray-300 cursor-pointer text-gray-700 peer-checked:bg-gray-800 peer-checked:text-white peer-checked:border-gray-800">○</span>
                                                        </label>
                                                    @else
                                                        <span class="block py-1.5 text-gray-300">×</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach

                    {{-- ③ お客様の情報 --}}
                    <div class="space-y-4 border-t pt-4">
                        <div class="font-semibold">③ お客様の情報</div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">お名前</label>
                            <input type="text" name="name" value="{{ old('name') }}" required class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">電話番号</label>
                            <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="09012345678" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-700 mb-1">メールアドレス（確認メールをお送りします）</label>
                            <input type="email" name="email" value="{{ old('email') }}" required class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                        </div>

                        <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">この内容で予約する</button>
                    </div>
                @endif
            </form>
        </div>
    @endif
@endsection
