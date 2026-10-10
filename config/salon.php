<?php

return [
    // お客様の施術写真の保存先。ローカルは public、本番は s3 にする（.env の PHOTO_DISK）
    'photo_disk' => env('PHOTO_DISK', 'public'),
];
