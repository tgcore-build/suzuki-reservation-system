<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 事前カウンセリング（ご来店前に、お客様が入力する）
            $table->json('concerns')->nullable()->after('notes');           // お悩みの項目（config/salon.php の concerns のキー）
            $table->text('concern_note')->nullable()->after('concerns');    // お悩み・ご要望の自由記入
            $table->timestamp('counseled_at')->nullable()->after('concern_note'); // 入力した日時（未入力は NULL）
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['concerns', 'concern_note', 'counseled_at']);
        });
    }
};
