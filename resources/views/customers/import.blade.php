<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客のCSV取り込み</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4 text-sm">
                @if ($errors->any())
                    <div class="p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
                @endif

                <p>CSVファイルを選ぶと、登録前に内容を確認できます（まだ登録はされません）。</p>
                <ul class="list-disc list-inside text-gray-600">
                    <li>1行目は見出しにしてください。「氏名」と「電話番号」の列は必須です。</li>
                    <li>ほかに「メールアドレス」「生年月日」「住所」「メモ」の列を読み取ります。</li>
                    <li>同じ電話番号のお客様がすでにいる行は、取り込まずにスキップします。</li>
                    <li>文字コードはUTF-8とShift-JISに対応しています。</li>
                </ul>
                <a href="{{ route('customer-import.template') }}" class="text-indigo-600 underline">サンプルCSVをダウンロード</a>

                <form method="POST" action="{{ route('customer-import.preview') }}" enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="file" name="file" accept=".csv,text/csv" required>
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">内容を確認する</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
