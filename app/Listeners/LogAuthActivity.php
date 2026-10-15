<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;

// ログイン関係の出来事をログに残す（不正ログインの調査用。パスワードは記録しない）
class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        Log::info('ログイン成功', ['user_id' => $event->user->getAuthIdentifier(), 'ip' => request()->ip()]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::warning('ログイン失敗', ['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()]);
    }

    public function handleLockout(Lockout $event): void
    {
        Log::warning('ログイン試行回数の上限に達しました', ['email' => $event->request->input('email'), 'ip' => $event->request->ip()]);
    }

    public function handleLogout(Logout $event): void
    {
        Log::info('ログアウト', ['user_id' => $event->user?->getAuthIdentifier(), 'ip' => request()->ip()]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        Log::info('パスワード再設定', ['user_id' => $event->user->getAuthIdentifier(), 'ip' => request()->ip()]);
    }
}
