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
        $rows = $this->parse($text);

        if ($rows === null) {
            return back()->withErrors(['file' => '1行目の見出しに「氏名」と「電話番号」の列が見つかりません。']);
        }

        session(['customer_import' => $rows]);

        return view('customers.import_preview', ['rows' => collect($rows)]);
    }

    public function confirm(): RedirectResponse
    {
        $rows = collect(session('customer_import'));

        if ($rows->isEmpty()) {
            return redirect()->route('customer-import.create')
                ->withErrors(['file' => '取り込むデータがありません。もう一度ファイルを選んでください。']);
        }

        $new = $rows->where('status', 'new');

        DB::transaction(function () use ($new) {
            foreach ($new as $row) {
                Customer::create($row['data']);
            }
        });

        session()->forget('customer_import');

        $dup = $rows->where('status', 'duplicate')->count();
        $err = $rows->where('status', 'error')->count();

        return redirect()->route('customers.index')
            ->with('status', "{$new->count()}件を登録しました（重複{$dup}件・エラー{$err}件は取り込んでいません）。");
    }

    // Excelで保存したCSVはShift-JISのことが多いので、文字コードをUTF-8にそろえる
    private function toUtf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $encoding = mb_detect_encoding($text, ['UTF-8', 'SJIS-win', 'EUC-JP'], true) ?: 'SJIS-win';

        return $encoding === 'UTF-8' ? $text : mb_convert_encoding($text, 'UTF-8', $encoding);
    }

    private function parse(string $text): ?array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $header = fgetcsv($stream);
        if (! $header) {
            return null;
        }

        $map = [];
        foreach ($header as $i => $h) {
            $h = mb_strtolower(trim((string) $h));
            foreach (self::COLUMNS as $key => $aliases) {
                if (in_array($h, array_map('mb_strtolower', $aliases), true)) {
                    $map[$key] = $i;
                    break;
                }
            }
        }
        if (! isset($map['name'], $map['phone'])) {
            return null;
        }

        $existing = Customer::pluck('phone')->filter()
            ->mapWithKeys(fn ($p) => [preg_replace('/\D/', '', $p) => true])->all();
        $seen = [];
        $rows = [];
        $line = 1;

        while (($cols = fgetcsv($stream)) !== false) {
            $line++;
            if (count($cols) === 1 && trim((string) $cols[0]) === '') {
                continue;
            }

            $get = fn (string $key) => isset($map[$key]) ? trim((string) ($cols[$map[$key]] ?? '')) : '';

            $name = $get('name');
            $phone = $this->normalizePhone($get('phone'));
            $email = $get('email');
            $birthday = $this->normalizeBirthday($get('birthday'));

            $status = 'new';
            $reason = '';
            if ($name === '') {
                [$status, $reason] = ['error', '氏名が空です'];
            } elseif ($phone === null) {
                [$status, $reason] = ['error', '電話番号の形式が正しくありません'];
            } elseif ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                [$status, $reason] = ['error', 'メールアドレスの形式が正しくありません'];
            } elseif ($get('birthday') !== '' && $birthday === null) {
                [$status, $reason] = ['error', '生年月日の形式が正しくありません（例：1980-05-20）'];
            } elseif (isset($existing[$phone])) {
                [$status, $reason] = ['duplicate', '同じ電話番号のお客様が登録済みです'];
            } elseif (isset($seen[$phone])) {
                [$status, $reason] = ['duplicate', '同じ電話番号がファイル内で重複しています'];
            } else {
                $seen[$phone] = true;
            }

            $rows[] = [
                'line' => $line,
                'status' => $status,
                'reason' => $reason,
                'data' => [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email ?: null,
                    'birthday' => $birthday,
                    'address' => $get('address') ?: null,
                    'memo' => $get('memo') ?: null,
                ],
            ];
        }
        fclose($stream);

        return $rows;
    }

    // 数字だけにそろえる。Excelで先頭の0が消えた携帯番号（9012345678）は0を補う
    private function normalizePhone(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value);
        if (strlen($digits) === 10 && preg_match('/^[789]/', $digits)) {
            $digits = '0' . $digits;
        }

        return in_array(strlen($digits), [10, 11], true) ? $digits : null;
    }

    private function normalizeBirthday(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace('日', '', str_replace(['年', '月', '/'], '-', $value));
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
}
