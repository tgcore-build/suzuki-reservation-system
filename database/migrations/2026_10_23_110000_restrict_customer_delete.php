<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 顧客を消すと予約・施術記録まで消える設定（cascade）をやめ、予約・記録がある顧客は消せないようにする
    public function up(): void
    {
        foreach (['reservations', 'records'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['customer_id']);
                $t->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['reservations', 'records'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropForeign(['customer_id']);
                $t->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            });
        }
    }
};
