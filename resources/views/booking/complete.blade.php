@extends('booking.layout')

@section('content')
    <div class="bg-white shadow-sm rounded-lg p-6 text-center space-y-3">
        <h2 class="font-semibold text-lg">ご予約を承りました</h2>
        <p class="text-sm">{{ $booked['at'] }}　{{ $booked['menu'] }}</p>
        <p class="text-sm text-gray-600">確認メールをお送りしました。ご来店をお待ちしております。</p>
        @if ($booked['new_customer'] ?? false)
            <div class="mt-4 p-4 bg-gray-50 rounded text-sm space-y-2">
                <p>はじめてのご来店の前に、髪のお悩みやご要望をお聞かせください（1分ほどで終わります）。</p>
                <a href="{{ route('my.counseling', $booked['reservation_id']) }}" class="inline-block px-4 py-2 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">事前カウンセリングに進む</a>
            </div>
        @endif
        <a href="{{ route('booking.index') }}" class="inline-block text-sm text-gray-600 underline">トップへ戻る</a>
    </div>
@endsection
