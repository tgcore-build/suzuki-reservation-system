<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            // 提案したメニューのID（メニュー名を変えても、過去の提案とつながるように）
            // result_menu は、提案した時点のメニュー名として残す
            $table->foreignId('result_menu_id')->nullable()->after('result_menu')->constrained('menus')->nullOnDelete();
        });

        // これまでの提案は、メニュー名からIDを埋める
        DB::table('ai_sessions')->whereNotNull('result_menu')->update([
            'result_menu_id' => DB::raw('(SELECT id FROM menus WHERE menus.name = ai_sessions.result_menu ORDER BY id LIMIT 1)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('result_menu_id');
        });
    }
};
