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
        @if (session('line_error'))
            <div class="p-3 bg-red-100 text-red-800 rounded-sm text-sm">{{ session('line_error') }}</div>
        @endif

        @if ($lineEnabled)
            <div class="bg-white shadow-xs rounded-lg p-5">
                <h2 class="font-semibold mb-2">LINE 連携</h2>
                @if ($customer->line_user_id)
                    <p class="text-sm text-gray-700">連携済み（{{ $customer->line_linked_at?->format('Y年n月j日') }}）。ご予約のリマインドを LINE でお送りします。</p>
                    <form method="POST" action="{{ route('my.line.unlink') }}" class="mt-3" onsubmit="return confirm('LINE との連携を解除します。よろしいですか？');">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 underline">連携を解除する</button>
                    </form>
                @else
                    <p class="text-sm text-gray-700 mb-3">LINE と連携すると、ご予約の前日のリマインドを LINE でお送りします（連携しない場合は、メールでお送りします）。</p>
                    <a href="{{ route('my.line') }}" class="inline-block px-4 py-2 rounded-md font-semibold text-sm text-white" style="background-color: #06C755;">LINE と連携する</a>
                    <p class="mt-2 text-xs text-gray-500">LINE の画面で「許可する」を押してください。お店の公式アカウントを友だち追加していない場合は、同じ画面で追加できます。</p>
                @endif
            </div>
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
