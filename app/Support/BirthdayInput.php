<?php

namespace App\Support;

// 生年月日の入力を「Y-m-d」にそろえる。
// カレンダー部品は Y-m-d で送るが、直接入力や古い画面からの「1990/1/1」「1990-01-01」「19900101」「１９９０／１／１」も受け付ける
class BirthdayInput
{
    public const MESSAGE = '生年月日は、1990/01/01 のように入力してください。';

    // そろえられない値（存在しない日付など）は、そのまま返す（このあとの入力チェックでエラーにする）
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $s = trim(mb_convert_kana($value, 'n')); // 全角数字を半角に
        if (! preg_match('/^(\d{4})\D?(\d{1,2})\D?(\d{1,2})\D?$/u', $s, $m)) {
            return $value;
        }

        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : $value;
    }

    // 入力チェックの前に、リクエストの birthday をそろえる
    public static function prepare(\Illuminate\Http\Request $request): void
    {
        if ($request->has('birthday')) {
            $request->merge(['birthday' => self::normalize($request->input('birthday'))]);
        }
    }
}
