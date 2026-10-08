@extends('booking.layout')

@section('content')
    <div class="bg-white shadow-sm rounded-lg p-6 text-center space-y-3">
        <h2 class="font-semibold text-lg">ご予約を承りました</h2>
        <p class="text-sm">{{ $booked['at'] }}　{{ $booked['menu'] }}</p>
        <p class="text-sm text-gray-600">確認メールをお送りしました。ご来店をお待ちしております。</p>
        <a href="{{ route('booking.index') }}" class="inline-block text-sm text-gray-600 underline">トップへ戻る</a>
    </div>
@endsection
