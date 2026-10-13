<?php

// AI提案機能の設定。使うAI APIを変えるときは、app/Services/Ai/MenuAdvisor.php を差し替える。
return [
    'timeout' => (int) env('AI_TIMEOUT', 15),          // AIの応答を待つ秒数
    'daily_limit' => (int) env('AI_DAILY_LIMIT', 200), // 1日のAIへの問い合わせ回数の上限（費用の暴走を防ぐ）
    'max_turns' => (int) env('AI_MAX_TURNS', 4),       // お客様の発言が何回目で、必ず提案して終えるか

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite'),
    ],
];
