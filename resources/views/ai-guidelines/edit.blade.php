<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI相談の設定</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 bg-green-100 text-green-800 rounded-sm text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-1">お店の判断基準</h3>
                <p class="text-sm text-gray-600 mb-4">
                    よくある悩みと、すすめるメニューの対応を書いてください。AIは、この内容をいちばん大切な基準として提案します。
                    メニューごとの「どんな悩みの人向けか」は、<a href="{{ route('menus.index') }}" class="text-indigo-600 underline">メニュー管理</a>の「説明」に書くと、AIに伝わります。
                </p>

                <form method="POST" action="{{ route('ai-guidelines.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <textarea name="guidelines" rows="12" maxlength="3000"
                        class="w-full border-gray-300 rounded-md shadow-xs text-sm"
                        placeholder="例：&#10;・頭皮のかゆみ・フケ・におい・乾燥 → ヘッドスパ（ベーシック／炭酸スパ／ロングスパ）&#10;・頭皮が硬い・髪のボリュームが気になる → 炭酸スパ&#10;・かゆみや赤みがひどい、長く続く → 皮膚科の受診もすすめる">{{ old('guidelines', $guidelines) }}</textarea>
                    <x-input-error :messages="$errors->get('guidelines')" class="mt-2" />
                    <x-primary-button>保存する</x-primary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
