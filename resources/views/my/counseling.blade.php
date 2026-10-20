@extends('booking.layout')

@section('title', '事前カウンセリング')

@section('content')
    <div class="bg-white shadow-sm rounded-lg p-5 max-w-xl mx-auto">
        <h2 class="font-semibold mb-1">事前カウンセリング</h2>
        <p class="text-sm text-gray-600 mb-4">
            ご予約：{{ $reservation->scheduled_at->format('Y年n月j日 H:i') }}　{{ $reservation->menu?->name }}<br>
            気になることにチェックをつけてください。ご来店のときに、担当者が内容を確認してからご案内します。
        </p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('my.counseling', $reservation) }}" class="space-y-4">
            @csrf
            @method('PUT')
            @php $checked = old('concerns', $reservation->concerns ?? []); @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach (config('salon.concerns') as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="concerns[]" value="{{ $key }}" @checked(in_array($key, $checked, true)) class="rounded border-gray-300">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <div>
                <label for="concern_note" class="block text-sm text-gray-700 mb-1">そのほかのお悩み・ご要望（任意）</label>
                <textarea id="concern_note" name="concern_note" rows="3" maxlength="1000" class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('concern_note', $reservation->concern_note) }}</textarea>
            </div>
            <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">送信する</button>
        </form>

        <a href="{{ route('booking.index') }}" class="mt-4 inline-block text-sm text-gray-600 underline">トップへ戻る</a>
    </div>
@endsection
