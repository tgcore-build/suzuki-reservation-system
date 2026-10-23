<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\Request;

// お客様の簡易ログイン（管理者のログインとは別）
//  1段階目：電話番号＋生年月日 … 予約のときに、名前などの入力を省ける
//  2段階目：登録メールに届く6桁のコード … 利用履歴（個人情報）を見られる
class CustomerLogin
{
    private const CUSTOMER_KEY = 'customer_login.id';
    private const VERIFIED_KEY = 'customer_login.verified_at';

    public const CODE_MINUTES = VerificationCode::CODE_MINUTES; // コードの有効期間（分）
    public const VERIFIED_MINUTES = 30; // 2段階目の確認が有効な時間（分）。過ぎたら、もう一度コードを入力する

    public function __construct(private VerificationCode $codes)
    {
    }

    public function findByPhone(string $phone): ?Customer
    {
        $digits = preg_replace('/\D/', '', $phone);

        return $digits === '' ? null : Customer::whereRaw("REPLACE(phone, '-', '') = ?", [$digits])->first();
    }

    public function findByPhoneAndBirthday(string $phone, string $birthday): ?Customer
    {
        $customer = $this->findByPhone($phone);

        return $customer && $customer->birthday?->format('Y-m-d') === $birthday ? $customer : null;
    }

    public function login(Request $request, Customer $customer): void
    {
        $request->session()->regenerate(); // ログインの前後でセッションIDを変える（セッション固定攻撃の対策）
        $request->session()->put(self::CUSTOMER_KEY, $customer->id);
        $request->session()->forget(self::VERIFIED_KEY);
    }

    public function logout(Request $request): void
    {
        $request->session()->forget([self::CUSTOMER_KEY, self::VERIFIED_KEY]);
        $request->session()->regenerate();
    }

    public function customer(Request $request): ?Customer
    {
        $id = $request->session()->get(self::CUSTOMER_KEY);

        return $id ? Customer::find($id) : null;
    }

    public function isVerified(Request $request): bool
    {
        $at = $request->session()->get(self::VERIFIED_KEY);

        return $at && now()->timestamp - $at < self::VERIFIED_MINUTES * 60;
    }

    // 新しいコードを作る（前のコードは使えなくなる）
    public function issueCode(Customer $customer): string
    {
        return $this->codes->issue($this->codeKey($customer));
    }

    // コードが正しければ、2段階目まで確認できた状態にする
    public function verifyCode(Request $request, Customer $customer, string $code): bool
    {
        if (! $this->checkCode($customer, $code)) {
            return false;
        }

        $this->markVerified($request, $customer);

        return true;
    }

    // ログインと2段階目の確認を、まとめて済ませた状態にする（生年月日をコードで確認して登録したときなど）
    public function markVerified(Request $request, Customer $customer): void
    {
        $this->login($request, $customer);
        $request->session()->put(self::VERIFIED_KEY, now()->timestamp);
    }

    // コードが正しいか確認する（正しければ、そのコードは使えなくなる）
    public function checkCode(Customer $customer, string $code): bool
    {
        return $this->codes->check($this->codeKey($customer), $code);
    }

    // メールアドレスの一部を隠す（例：h****@example.com）
    public static function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1) . '****@' . $domain; // 文字数も分からないように、* は常に4つ
    }

    private function codeKey(Customer $customer): string
    {
        return 'customer-login-code:' . $customer->id;
    }
}
