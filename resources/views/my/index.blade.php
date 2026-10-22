@extends('booking.layout')

@section('title', 'ご利用履歴')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-sm">{{ $customer->name }} 様</p>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('booking.index') }}" class="underline">予約する</a>
                <form method="POST" action="{{ route('my.logout') }}">
                    @csrf
                    <button type="submit" class="underline text-gray-600">ログアウト</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="p-3 bg-green-100 text-green-800 rounded-sm text-sm">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-xs rounded-lg p-5">
            <h2 class="font-semibold mb-3">これからのご予約</h2>
            @forelse ($upcoming as $r)
                <div class="border-t py-2 text-sm flex items-center justify-between gap-2">
                    <span>{{ $r->scheduled_at->format('Y年n月j日 H:i') }}　{{ $r->menu?->name ?? '—' }}</span>
                    <a href="{{ route('my.counseling', $r) }}" class="text-xs underline whitespace-nowrap">{{ $r->counseled_at ? '事前カウンセリングを直す' : '事前カウンセリング' }}</a>
                </div>
            @empty
                <p class="text-sm text-gray-500">ご予約はありません。</p>
            @endforelse
        </div>

        <div class="bg-white shadow-xs rounded-lg p-5">
            <h2 class="font-semibold mb-3">ご来店の記録</h2>
            @forelse ($records as $record)
                <div class="border-t py-4 text-sm">
                    <div class="font-semibold">{{ $record->performed_on->format('Y年n月j日') }}　{{ $record->menu }}</div>
                    @if ($record->concernLabels() || $record->concern_note)
                        <div class="mt-2">
                            <div class="text-xs text-gray-500">カウンセリング（お悩み・ご要望）</div>
                            @if ($record->concernLabels())
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($record->concernLabels() as $label)
                                        <span class="px-2 py-0.5 rounded-sm bg-gray-100 text-xs">{{ $label }}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if ($record->concern_note)<div class="mt-1 whitespace-pre-line">{{ $record->concern_note }}</div>@endif
                        </div>
                    @endif
                    @if ($record->photo_before || $record->photo_after)
                        <div class="mt-3 flex gap-3">
                            @if ($record->photo_before)<div><div class="text-xs text-gray-500">施術前</div><img src="{{ $record->photoUrl('photo_before') }}" alt="施術前の写真" class="w-32 rounded-sm"></div>@endif
                            @if ($record->photo_after)<div><div class="text-xs text-gray-500">施術後</div><img src="{{ $record->photoUrl('photo_after') }}" alt="施術後の写真" class="w-32 rounded-sm"></div>@endif
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500">まだ記録はありません。</p>
            @endforelse
        </div>

        <div class="bg-white shadow-xs rounded-lg p-5">
            <h2 class="font-semibold mb-3">過去のご予約</h2>
            @forelse ($pastReservations as $r)
                <div class="border-t py-2 text-sm">{{ $r->scheduled_at->format('Y年n月j日') }}　{{ $r->menu?->name ?? '—' }}</div>
            @empty
                <p class="text-sm text-gray-500">過去のご予約はありません。</p>
            @endforelse
        </div>

        @if ($aiSessions->isNotEmpty())
            <div class="bg-white shadow-xs rounded-lg p-5">
                <h2 class="font-semibold mb-3">AI相談</h2>
                @foreach ($aiSessions as $s)
                    <div class="border-t py-3 text-sm">
                        <div class="text-gray-500 text-xs">{{ $s->created_at->format('Y年n月j日') }}</div>
                        <div class="mt-1">ご相談：{{ $s->messages->where('role', 'user')->pluck('content')->implode(' ／ ') }}</div>
                        <div class="mt-1">ご提案：{{ $s->resultMenuName() ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
