<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

// 最初の管理者を作る（本番で、コンテナが起動するたびに実行される）
// 環境変数 ADMIN_EMAIL・ADMIN_PASSWORD があるときだけ作る。すでにその管理者がいれば、何も変えない
// （管理画面でパスワードを変えたあとに、起動のたびに元に戻ってしまわないように）
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('salon.admin.email');
        $password = config('salon.admin.password');

        if (! $email || ! $password) {
            $this->command?->info('ADMIN_EMAIL / ADMIN_PASSWORD が未設定のため、管理者は作りません。');

            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->command?->info('管理者はすでにいます（パスワードは変えません）。');

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error('ADMIN_PASSWORD は12文字以上にしてください。管理者は作りません。');

            return;
        }

        $user = User::create(['name' => '管理者', 'email' => $email, 'password' => $password]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->command?->info("管理者を作りました: {$email}");
    }
}
