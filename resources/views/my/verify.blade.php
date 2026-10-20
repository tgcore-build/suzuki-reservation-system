@extends('booking.layout')

@section('title', 'ご利用履歴')

@section('content')
    <div class="bg-white shadow-sm rounded-lg p-5 max-w-md mx-auto">
        <h2 class="font-semibold mb-2">確認コードの入力</h2>

        @if (! $maskedEmail)
            <p class="text-sm text-gray-600">メールアドレスが登録されていないため、ご利用履歴を表示できません。お手数ですが、お店にお問い合わせください。</p>
        @else
            <p class="text-sm text-gray-600 mb-4">
                ご利用履歴には個人情報が含まれるため、ご本人の確認をお願いしています。<br>
                ご登録のメールアドレス（{{ $maskedEmail }}）に、6桁の確認コードをお送りします。
            </p>

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @if ($codeSent)
                <form method="POST" action="{{ route('my.verify') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="code" class="block text-sm text-gray-700 mb-1">確認コード（6桁）</label>
                        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="w-full border-gray-300 rounded-md shadow-sm text-lg tracking-widest">
                    </div>
                    <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">確認する</button>
                </form>
            @endif

            <form method="POST" action="{{ route('my.verify.send') }}" class="mt-4">
                @csrf
                <button type="submit" class="{{ $codeSent ? 'text-sm underline text-gray-600' : 'w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700' }}">
                    {{ $codeSent ? 'コードを送り直す' : '確認コードを送る' }}
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('my.logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="text-xs text-gray-500 underline">ログアウト</button>
        </form>
    </div>
@endsection
