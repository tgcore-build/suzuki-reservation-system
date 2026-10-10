<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportController extends Controller
{
    // 見出し行の名前 → 取り込む項目（別のソフトの見出しでも拾えるよう、複数の呼び方を許可）
    private const COLUMNS = [
        'name' => ['氏名', '名前', 'お名前', 'name'],
        'phone' => ['電話番号', '電話', '携帯', '携帯電話', 'tel', 'phone'],
        'email' => ['メール', 'メールアドレス', 'email', 'e-mail'],
        'birthday' => ['生年月日', '誕生日', 'birthday'],
        'address' => ['住所', 'address'],
        'memo' => ['メモ', '備考', 'memo'],
    ];

    public function create(): View
    {
        return view('customers.import');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "氏名,電話番号,メールアドレス,生年月日,住所,メモ\n";
            echo "山田 花子,09000000001,hanako@example.com,1980-05-20,名古屋市,サンプル\n";
        }, 'customers_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']], [], ['file' => 'CSVファイル']);

        $text = $this->toUtf8(file_get_contents($request->file('file')->getRealPath()));
        $rows =
cat > resources/views/customers/import.blade.php <<'EOF'
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
