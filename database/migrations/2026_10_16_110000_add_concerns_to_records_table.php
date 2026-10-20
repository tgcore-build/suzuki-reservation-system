<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // カウンセリングシート（お客様にも見せる）。memo はスタッフ向けなので分ける
            $table->json('concerns')->nullable()->after('menu');       // チェックしたお悩みの項目（config/salon.php の concerns のキー）
            $table->text('concern_note')->nullable()->after('concerns'); // お悩み・ご要望の自由記入
        });
    }

    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            $table->dropColumn(['concerns', 'concern_note']);
        });
    }
};
