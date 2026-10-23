<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // 予約したときのメニューの所要時間（分）と料金（円）。あとでメニューを変えても、既存の予約は変わらない
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('menu_id');
            $table->unsignedInteger('price')->nullable()->after('duration_minutes');
        });

        // これまでの予約には、今のメニューの値を入れる
        DB::table('reservations')->update([
            'duration_minutes' => DB::raw('(SELECT duration_minutes FROM menus WHERE menus.id = reservations.menu_id)'),
            'price' => DB::raw('(SELECT price FROM menus WHERE menus.id = reservations.menu_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['duration_minutes', 'price']);
        });
    }
};
