<?php

return [
    // お客様の施術写真の保存先（.env の PHOTO_DISK）。どちらも非公開の場所
    //  local：storage/app/private（ローカル）。表示は、ログインした管理者・本人のお客様だけが通れるURLから
    //  s3：本番。表示は30分だけ有効な一時URL
    // public にはしない（URLを知っていれば、ログインなしで見られてしまうため）
    'photo_disk' => env('PHOTO_DISK', 'local'),

    // カウンセリングシートのお悩みの項目（対応記録でチェックする。お客様の利用履歴ページにも表示する）
    'concerns' => [
        'dryness' => '乾燥・パサつき',
        'frizz' => '広がり・うねり',
        'damage' => '傷み・枝毛',
        'volume' => 'ボリュームが出ない',
        'gray' => '白髪',
        'color_fade' => '色落ち・退色',
        'scalp_itch' => '頭皮のかゆみ・乾燥',
        'scalp_oil' => '頭皮のべたつき・におい',
        'styling' => 'まとまらない・スタイリングしにくい',
    ],
];
