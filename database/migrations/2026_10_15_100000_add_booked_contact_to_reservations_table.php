<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // オンライン予約で入力された名前・メール（登録済みの顧客情報と違う場合の確認用）
            $table->string('booked_name', 100)->nullable()->after('source');
            $table->string('booked_email')->nullable()->after('booked_name');
            $table->boolean('needs_review')->default(false)->after('booked_email');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['booked_name', 'booked_email', 'needs_review']);
        });
    }
};
