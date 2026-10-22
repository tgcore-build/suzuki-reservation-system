@extends('booking.layout')

@section('title', $next === 'book' ? 'かんたんログイン' : 'ご利用履歴')

@section('content')
    <div class="bg-white shadow-xs rounded-lg p-5 max-w-md mx-auto">
        <h2 class="font-semibold mb-2">{{ $next === 'book' ? 'かんたんログイン' : 'ご利用履歴を見る' }}</h2>
        <p class="text-sm text-gray-600 mb-4">
            @if ($next === 'book')
                ご登録済みの方は、電話番号と生年月日でログインしてからご予約ください。
            @else
                ご登録の電話番号と生年月日を入力してください。次に、ご登録のメールアドレスに届く確認コードを入力していただきます。
            @endif
        </p>

        <form method="POST" action="{{ route('my.login') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="next" value="{{ $next }}">
            @if ($menuId)<input type="hidden" name="menu_id" value="{{ $menuId }}">@endif

            @if ($errors->any())
                <div class="p-3 bg-red-100 text-red-800 rounded-sm text-sm">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div>
                <label for="phone" class="block text-sm text-gray-700 mb-1">電話番号</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" placeholder="09012345678" class="w-full border-gray-300 rounded-md shadow-xs text-sm">
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="birthday" class="block text-sm text-gray-700 mb-1">生年月日</label>
                <input id="birthday" type="text" name="birthday" value="{{ old('birthday') }}" required data-birthday autocomplete="bday" placeholder="1990/01/01" class="w-full border-gray-300 rounded-md shadow-xs text-sm">
                @error('birthday')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="w-full px-4 py-3 bg-gray-800 rounded-md font-semibold text-sm text-white hover:bg-gray-700">ログイン</button>
        </form>

        <div class="mt-4 text-xs text-gray-500 space-y-1">
            <p>生年月日を登録していない方は、<a href="{{ route('my.birthday') }}" class="underline">こちらから登録</a>してください（ご登録のメールアドレスで確認します）。</p>
            <p>はじめての方は、<a href="{{ route('booking.index') }}" class="underline">ご予約の画面</a>からお客様情報を登録して、ご予約いただけます。</p>
        </div>
    </div>
@endsection
