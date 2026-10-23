<?php

namespace App\Services\Line;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

// LINE とのやり取りをまとめる（LINE ログインでユーザーIDを受け取る／Messaging API でメッセージを送る）
class LineClient
{
    private const AUTHORIZE_URL = 'https://access.line.me/oauth2/v2.1/authorize';
    private const API = 'https://api.line.me';

    // LINE ログインの設定があるか（なければ連携ボタンを出さない）
    public function loginEnabled(): bool
    {
        return filled(config('line.login_channel_id')) && filled(config('line.login_channel_secret'));
    }

    // リマインドを LINE で送れるか
    public function messagingEnabled(): bool
    {
        return filled(config('line.messaging_access_token'));
    }

    // LINE のログイン画面のURL。bot_prompt=normal で、許可の画面に公式アカウントの友だち追加も出す
    public function authorizeUrl(string $redirectUri, string $state, string $nonce): string
    {
        return self::AUTHORIZE_URL . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => config('line.login_channel_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'profile openid',
            'nonce' => $nonce,
            'bot_prompt' => 'normal',
        ]);
    }

    // LINE から戻ってきたときのコードを使って、お客様のユーザーIDと、公式アカウントの友だちかどうかを受け取る
    public function fetchUser(string $code, string $redirectUri, string $nonce): array
    {
        $token = $this->http()->asForm()->post(self::API . '/oauth2/v2.1/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config('line.login_channel_id'),
            'client_secret' => config('line.login_channel_secret'),
        ]);
        if ($token->failed() || ! $token->json('id_token') || ! $token->json('access_token')) {
            throw new LineException('LINE のトークンを受け取れませんでした（HTTP ' . $token->status() . '）');
        }

        // ID トークンを LINE に確認してもらい、ユーザーID（sub）を受け取る。nonce が合うかも確認する
        $verified = $this->http()->asForm()->post(self::API . '/oauth2/v2.1/verify', [
            'id_token' => $token->json('id_token'),
            'client_id' => config('line.login_channel_id'),
            'nonce' => $nonce,
        ]);
        $userId = $verified->json('sub');
        if ($verified->failed() || ! is_string($userId) || ! preg_match('/^U[0-9a-f]{32}$/', $userId)) {
            throw new LineException('LINE のユーザーIDを確認できませんでした（HTTP ' . $verified->status() . '）');
        }

        $friendship = $this->http()->withToken($token->json('access_token'))->get(self::API . '/friendship/v1/status');

        return [
            'user_id' => $userId,
            'is_friend' => $friendship->successful() && $friendship->json('friendFlag') === true,
        ];
    }

    // 公式アカウントから、テキストを1通送る。同じ送信を二重に送らないように、再送用のキーを付ける
    public function push(string $userId, string $text): void
    {
        $response = $this->http()
            ->withToken(config('line.messaging_access_token'))
            ->withHeaders(['X-Line-Retry-Key' => (string) Str::uuid()])
            ->post(self::API . '/v2/bot/message/push', [
                'to' => $userId,
                'messages' => [['type' => 'text', 'text' => Str::limit($text, 4900)]],
            ]);

        if ($response->failed()) {
            throw new LineException('LINE の送信に失敗しました（HTTP ' . $response->status() . '）: ' . Str::limit($response->body(), 200));
        }
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('line.timeout'))->acceptJson();
    }
}
