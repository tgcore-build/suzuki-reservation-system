@extends('booking.layout')

@section('title', '事前カウンセリング')

@section('content')
    <div class="bg-white shadow-xs rounded-lg p-6 max-w-xl mx-auto text-center space-y-4">
        <div class="mx-auto w-12 h-12 rounded-full bg-green-100 text-green-700 flex items-center justify-center text-2xl" aria-hidden="true">✓</div>
        <h2 class="font-semibold text-lg">事前カウンセリングを送信しました</h2>
        <p class="text-sm text-gray-600">ありがとうございます。ご来店のときに、担当者が内容を確認してからご案内します。</p>

        <div class="text-left text-sm bg-gray-50 rounded-sm p-4 space-y-2">
            <div><span class="text-gray-500">ご予約：</span>{{ $reservation->scheduled_at->format('Y年n月j日 H:i') }}　{{ $reservation->menu?->name }}</div>
            <div>
                <span class="text-gray-500">お悩み：</span>
                {{ $reservation->concernLabels() ? implode('、', $reservation->concernLabels()) : '（チェックなし）' }}
            </div>
            @if ($reservation->concern_note)
                <div><span class="text-gray-500">ご要望：</span><span class="whitespace-pre-line">{{ $reservation->concern_note }}</span></div>
            @endif
        </div>

        <div class="flex flex-col items-center gap-2">
            <a href="{{ route('booking.index') }}" class="inline-block px-4 py-2 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">トップへ戻る</a>
            <a href="{{ route('my.counseling', $reservation) }}" class="text-sm text-gray-600 underline">内容を直す</a>
        </div>
    </div>
@endsection
