<?php

// AI提案機能の設定。使うAI APIを変えるときは、app/Services/Ai/MenuAdvisor.php を差し替える。
return [
    'timeout' => (int) env('AI_TIMEOUT', 8),           // AIの応答を待つ秒数（再試行を含めても、PHPの実行時間の上限30秒に収まるように）
    'daily_limit' => (int) env('AI_DAILY_LIMIT', 200), // 1日のAIへの問い合わせ回数の上限（費用の暴走を防ぐ）
    'ip_daily_limit' => (int) env('AI_IP_DAILY_LIMIT', 20), // 同じIPアドレスからの1日の上限（1人で使い切れないように）
    'max_turns' => (int) env('AI_MAX_TURNS', 4),       // お客様の発言が何回目で、必ず提案して終えるか

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    ],
];
