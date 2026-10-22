<?php

namespace App\Support;

// 電話番号を「数字だけ」にそろえる（予約画面・管理画面・CSV取り込みで共通）
// 使える文字は、数字・ハイフン・空白・かっこ（全角も可）。0から始まる10〜11桁だけを電話番号とみなす
class PhoneNumber
{
    public const MESSAGE = '電話番号は、0から始まる数字10〜11桁で入力してください（ハイフンはあってもなくても構いません）。';

    // そろえられない値は null
    public static function normalize(?string $value, bool $fixMissingZero = false): ?string
    {
        $s = trim(mb_convert_kana((string) $value, 'as')); // 全角の数字・記号・空白を半角に
        $s = str_replace(['ー', '‐', '−', '–', '—'], '-', $s);   // いろいろなハイフンをそろえる
        if ($s === '' || ! preg_match('/^[0-9\-\s()]+$/', $s)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $s);
        // Excel で先頭の0が消えた携帯番号（9012345678）は、0を補う（CSV取り込みだけ）
        if ($fixMissingZero && strlen($digits) === 10 && preg_match('/^[789]/', $digits)) {
            $digits = '0' . $digits;
        }

        return preg_match('/^0\d{9,10}$/', $digits) ? $digits : null;
    }

    // 入力チェック用のルール
    public static function rule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! is_string($value) || self::normalize($value) === null) {
                $fail(self::MESSAGE);
            }
        };
    }
}
