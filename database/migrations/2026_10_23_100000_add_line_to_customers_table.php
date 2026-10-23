<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // LINE ログインで受け取ったユーザーID（U から始まる33文字）。リマインドの送り先
            $table->string('line_user_id', 64)->nullable()->unique()->after('line_id');
            $table->timestamp('line_linked_at')->nullable()->after('line_user_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            // リマインドを何で送ったか（line / email）
            $table->string('reminder_channel', 10)->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['line_user_id']);
            $table->dropColumn(['line_user_id', 'line_linked_at']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('reminder_channel');
        });
    }
};
