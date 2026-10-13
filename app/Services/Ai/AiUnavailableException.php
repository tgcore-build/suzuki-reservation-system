<?php

namespace App\Services\Ai;

use RuntimeException;

// AIが使えない（キー未設定・通信失敗・想定外の応答）ときの例外
class AiUnavailableException extends RuntimeException
{
}
