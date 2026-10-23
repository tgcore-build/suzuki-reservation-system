@extends('booking.layout')

@section('content')
    <div class="bg-white shadow-xs rounded-lg p-5 max-w-md mx-auto">
        <h2 class="font-semibold mb-2">メールアドレスの確認</h2>
        <p class="text-sm text-gray-600 mb-4">
            {{ $pending['email'] }} に、6桁の確認コードをお送りしました。コードを入力すると、ご予約が確定します（有効期間は{{ $minutes }}分です）。
        </p>

        <div class="mb-4 text-sm bg-gray-50 rounded-sm p-3 space-y-1">
            <div><span class="text-gray-500">日時：</span>{{ \Carbon\Carbon::parse($pending['date'] . ' ' . $pending['time'])->format('Y年n月j日 H:i') }}</div>
            <div><span class="text-gray-500">メニュー：</span>{{ $menu?->name ?? '—' }}</div>
            <div><span class="text-gray-500">お名前：</span>{{ $pending['name'] }} 様</div>
        </div>

        @if (session('status'))
            <div class="mb-4 p-3 bg-green-100 text-green-800 rounded-sm text-sm">{{ session('status') }}</div>
        @endif
        @if ($errors->any() || session('code_error'))
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded-sm text-sm">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                @if (session('code_error'))<div>{{ session('code_error') }}</div>@endif
            </div>
        @endif

        <form method="POST" action="{{ route('booking.verify') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="block text-sm text-gray-700 mb-1">確認コード（6桁）</label>
                <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="w-full border-gray-300 rounded-md shadow-xs text-lg tracking-widest">
            </div>
            <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">確認して予約する</button>
        </form>

        <div class="mt-4 flex items-center justify-between text-sm">
            <form method="POST" action="{{ route('booking.verify.resend') }}">
                @csrf
                <button type="submit" class="underline text-gray-600">コードを送り直す</button>
            </form>
            <a href="{{ route('booking.index', ['menu_id' => $pending['menu_id']]) }}" class="underline text-gray-600">入力をやり直す</a>
        </div>
    </div>
@endsection
