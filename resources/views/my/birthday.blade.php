@extends('booking.layout')

@section('title', '生年月日の登録')

@section('content')
    <div class="bg-white shadow-xs rounded-lg p-5 max-w-md mx-auto">
        <h2 class="font-semibold mb-2">生年月日の登録</h2>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded-sm text-sm">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @if (! $pending)
            <p class="text-sm text-gray-600 mb-4">ご登録の電話番号を入力してください。ご登録のメールアドレスに、6桁の確認コードをお送りします。</p>
            <form method="POST" action="{{ route('my.birthday.send') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="phone" class="block text-sm text-gray-700 mb-1">電話番号</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="09012345678" class="w-full border-gray-300 rounded-md shadow-xs text-sm">
                </div>
                <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">確認コードを送る</button>
            </form>
        @else
            <p class="text-sm text-gray-600 mb-4">
                ご登録があり、生年月日が未登録の場合は、ご登録のメールアドレスに確認コードをお送りしました。<br>
                メールが届かない場合は、お手数ですがお店にお問い合わせください。
            </p>
            <form method="POST" action="{{ route('my.birthday') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm text-gray-700 mb-1">確認コード（6桁）</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required class="w-full border-gray-300 rounded-md shadow-xs text-lg tracking-widest">
                </div>
                <div>
                    <label for="birthday" class="block text-sm text-gray-700 mb-1">生年月日</label>
                    <input id="birthday" type="text" name="birthday" value="{{ old('birthday') }}" required data-birthday autocomplete="bday" placeholder="1990/01/01" class="w-full border-gray-300 rounded-md shadow-xs text-sm">
                    @error('birthday')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-gray-500">生年月日の月日は、お客様のカルテ番号に使います。</p>
                </div>
                <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">登録する</button>
            </form>
        @endif

        <a href="{{ route('my.login') }}" class="mt-4 inline-block text-sm text-gray-600 underline">ログイン画面に戻る</a>
    </div>
@endsection
