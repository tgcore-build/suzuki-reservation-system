<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

// メールで送る6桁の確認コード（かんたんログインの2段階目・はじめての予約のメール確認で共通）
// コードそのものは保存せず、ハッシュだけ保存する。10分で無効、5回まちがえると無効、1回使うと無効
class VerificationCode
{
    public const CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    // 新しいコードを作る（同じキーの前のコードは使えなくなる）
    public function issue(string $key): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->cacheKey($key), ['hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::CODE_MINUTES));

        return $code;
    }

    // 正しければ true（そのコードは使えなくなる）。まちがいが続いたら、コードを無効にする
    public function check(string $key, string $code): bool
    {
        $cacheKey = $this->cacheKey($key);
        $saved = Cache::get($cacheKey);
        if (! $saved) {
            return false;
        }

        if (Hash::check($code, $saved['hash'])) {
            Cache::forget($cacheKey);

            return true;
        }

        $saved['attempts']++;
        if ($saved['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $saved, now()->addMinutes(self::CODE_MINUTES));
        }

        return false;
    }

    private function cacheKey(string $key): string
    {
        return 'verification-code:' . $key;
    }
}
