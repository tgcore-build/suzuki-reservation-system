<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // カルテ番号を「生年月日の月日4桁-連番」（例：0202-1）にして、重ならないようにする
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('karte_number', 12)->nullable()->change();
        });

        // これまでの顧客には、同じ月日の中で、登録した順に連番を付ける
        $counts = [];
        foreach (DB::table('customers')->whereNotNull('birthday')->orderBy('created_at')->orderBy('id')->get(['id', 'birthday']) as $c) {
            $md = date('md', strtotime($c->birthday));
            $counts[$md] = ($counts[$md] ?? 0) + 1;
            DB::table('customers')->where('id', $c->id)->update(['karte_number' => "{$md}-{$counts[$md]}"]);
        }
        DB::table('customers')->whereNull('birthday')->update(['karte_number' => null]);

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('karte_number');
        });

        // 月日ごとに、最後に付けた連番を記録する（顧客を削除しても、その番号を次の人に使い回さないため）
        Schema::create('karte_sequences', function (Blueprint $table) {
            $table->char('month_day', 4)->primary();
            $table->unsignedInteger('last_number');
        });
        foreach ($counts as $md => $n) {
            DB::table('karte_sequences')->insert(['month_day' => $md, 'last_number' => $n]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('karte_sequences');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['karte_number']);
        });
        DB::table('customers')->whereNotNull('karte_number')->update(['karte_number' => DB::raw('SUBSTR(karte_number, 1, 4)')]);
        Schema::table('customers', function (Blueprint $table) {
            $table->string('karte_number', 4)->nullable()->change();
        });
    }
};
