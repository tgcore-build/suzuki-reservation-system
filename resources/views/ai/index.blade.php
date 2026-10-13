@extends('booking.layout')

@section('content')
    @php $advice = session('advice'); @endphp

    <div class="bg-white shadow-sm rounded-lg p-5">
        <h2 class="font-semibold mb-1">メニュー選びのご相談</h2>
        <p class="text-sm text-gray-600 mb-4">髪のお悩みやご要望を、お書きください。AIが、合いそうなメニューをご提案します。</p>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('ai.store') }}" class="space-y-3" onsubmit="this.querySelector('button[type=submit]').disabled = true">
            @csrf
            <textarea name="message" rows="4" maxlength="500" required placeholder="例：髪の乾燥が気になります。まとまりやすくしたいです。"
                class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('message', $advice['message'] ?? '') }}</textarea>
            <p class="text-xs text-gray-500">※ 500文字までです。お名前・電話番号など、個人が特定できる情報は入力しないでください。</p>
            <button type="submit" class="px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">相談する</button>
        </form>
    </div>

    @if ($advice)
        <div class="mt-6 bg-white shadow-sm rounded-lg p-5">
            @if ($advice['menu_id'])
                <div class="text-xs text-gray-500">おすすめのメニュー</div>
                <div class="text-lg font-semibold mb-2">{{ $advice['menu_name'] }}</div>
                <p class="text-sm mb-4">{{ $advice['reason'] }}</p>
                <a href="{{ route('booking.index', ['menu_id' => $advice['menu_id']]) }}"
                   class="inline-block px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white hover:bg-gray-700">このメニューで予約に進む</a>
            @else
                <p class="text-sm mb-2">{{ $advice['reason'] }}</p>
                <p class="text-sm text-gray-600">ご来店時に、直接ご相談ください。</p>
            @endif
            <p class="mt-4 text-xs text-gray-500">※ AIによる提案です。最終的なメニューは、ご来店時にご相談のうえ決めましょう。</p>
        </div>
    @endif

    <p class="mt-6 text-center text-sm"><a href="{{ route('booking.index') }}" class="text-gray-600 underline">予約画面に戻る</a></p>
@endsection
