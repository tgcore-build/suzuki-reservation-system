@extends('booking.layout')

@section('content')
    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="GET" action="{{ route('booking.index') }}" class="bg-white shadow-sm rounded-lg p-5 space-y-4">
        <div>
            <label class="block text-sm text-gray-700 mb-1">メニュー</label>
            <select name="menu_id" required class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                <option value="">選択してください</option>
                @foreach ($menus as $m)
                    <option value="{{ $m->id }}" @selected($menu && $menu->id === $m->id)>
                        {{ $m->name }}（約{{ $m->duration_minutes }}分・¥{{ number_format($m->price) }}）
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm text-gray-700 mb-1">ご希望の日</label>
            <input type="date" name="date" required
                   min="{{ today()->format('Y-m-d') }}" max="{{ today()->addDays(60)->format('Y-m-d') }}"
                   value="{{ $date?->format('Y-m-d') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
        </div>
        <button type="submit" class="px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">空き時間を見る</button>
    </form>

    @if (request('date') && ! $date)
        <p class="mt-4 text-sm text-red-700">日付は、今日から60日以内でお選びください。</p>
    @endif

    @if ($slots !== null)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-5">
            <h2 class="font-semibold mb-3">{{ $date->isoFormat('M月D日（ddd）') }}　{{ $menu->name }}</h2>

            @if (count($slots) === 0)
                <p class="text-sm text-gray-600">この日は予約できる時間がありません（休業日、または満席です）。別の日をお選びください。</p>
            @else
                <form method="POST" action="{{ route('booking.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="menu_id" value="{{ $menu->id }}">
                    <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">

                    <div>
                        <div class="text-sm text-gray-700 mb-2">ご希望の時間</div>
                        <div class="grid grid-cols-4 gap-2">
                            @foreach ($slots as $slot)
                                <label>
                                    <input type="radio" name="time" value="{{ $slot }}" class="peer sr-only" required>
                                    <span class="block text-center py-2 border rounded-md text-sm cursor-pointer peer-checked:bg-gray-800 peer-checked:text-white">{{ $slot }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

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
                </form>
            @endif
        </div>
    @endif
@endsection
