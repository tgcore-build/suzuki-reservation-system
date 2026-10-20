<?php

return [
    // お客様の施術写真の保存先。ローカルは public、本番は s3 にする（.env の PHOTO_DISK）
    'photo_disk' => env('PHOTO_DISK', 'public'),

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
