@extends('booking.layout')

@section('content')
    @php $completed = $aiSession?->isCompleted(); @endphp

    <div class="bg-white shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-1">
            <h2 class="font-semibold">メニュー選びのご相談</h2>
            @if ($aiSession)
                <form method="POST" action="{{ route('ai.reset') }}">
                    @csrf
                    <button type="submit" class="text-xs text-gray-600 underline">最初からやり直す</button>
                </form>
            @endif
        </div>
        <p class="text-sm text-gray-600 mb-2">髪のお悩みやご要望を、お書きください。AIがいくつか質問しながら、合いそうなメニューをご提案します。</p>
        <p class="text-xs text-gray-500 mb-4 p-2 bg-gray-50 rounded">ご相談の内容は、メニューのご提案のため外部のAIサービス（Google Gemini）に送信され、当店でも記録として保存します。お名前・電話番号などの個人情報は書かないでください。</p>

        {{-- 会話のログ --}}
        <div class="space-y-3 mb-4">
            @if ($messages->isEmpty())
                <div class="flex">
                    <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-gray-100">どんなお悩みですか？（例：髪の乾燥が気になる、まとまりにくい など）</div>
                </div>
            @endif
            @foreach ($messages as $m)
                @if ($m->role === 'user')
                    <div class="flex justify-end">
                        <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-gray-800 text-white whitespace-pre-line">{{ $m->content }}</div>
                    </div>
                @else
                    <div class="flex">
                        <div class="max-w-[85%] rounded-lg px-3 py-2 text-sm bg-gray-100 whitespace-pre-line">{{ $m->content }}</div>
                    </div>
                @endif
            @endforeach
        </div>

        @if ($errors->any())
            <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($completed)
            {{-- 提案結果 --}}
            <div class="border-t pt-4">
                @if ($resultMenu)
                    <div class="text-xs text-gray-500">おすすめのメニュー</div>
                    <div class="text-lg font-semibold mb-3">{{ $resultMenu->name }}</div>
                    <a href="{{ route('booking.index', ['menu_id' => $resultMenu->id]) }}"
                       class="inline-block px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white hover:bg-gray-700">このメニューで予約に進む</a>
                @else
                    <p class="text-sm text-gray-600">ご来店時に、直接ご相談ください。</p>
                @endif
                <p class="mt-4 text-xs text-gray-500">※ AIによる提案です。最終的なメニューは、ご来店時にご相談のうえ決めましょう。</p>
            </div>
        @else
            <form method="POST" action="{{ route('ai.store') }}" class="space-y-3" onsubmit="this.querySelector('button[type=submit]').disabled = true">
                @csrf
                <textarea name="message" rows="3" maxlength="500" required placeholder="ここに入力してください"
                    class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('message') }}</textarea>
                <p class="text-xs text-gray-500">※ 500文字までです。お名前・電話番号など、個人が特定できる情報は入力しないでください。</p>
                <button type="submit" class="px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">送信する</button>
            </form>
        @endif
    </div>

    <p class="mt-6 text-center text-sm"><a href="{{ route('booking.index') }}" class="text-gray-600 underline">予約画面に戻る</a></p>
@endsection
