<?php

// LINE 連携の設定。値は .env に入れる（コミットしない）。
// LINE ログインと Messaging API のチャネルは、LINE Developers の「同じプロバイダー」に作る
// （プロバイダーが違うと、同じお客様でもユーザーIDが変わり、リマインドを送れない）
return [
    // LINE ログイン（お客様のユーザーIDを受け取る）
    'login_channel_id' => env('LINE_LOGIN_CHANNEL_ID'),
    'login_channel_secret' => env('LINE_LOGIN_CHANNEL_SECRET'),

    // Messaging API（公式アカウントからリマインドを送る）
    'messaging_access_token' => env('LINE_MESSAGING_ACCESS_TOKEN'),

    'timeout' => (int) env('LINE_TIMEOUT', 8), // LINE の応答を待つ秒数
];
