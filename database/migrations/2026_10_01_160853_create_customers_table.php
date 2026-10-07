<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('phone', 20)->unique();
            $table->string('email', 100)->nullable();
            $table->string('address', 255)->nullable();
            $table->date('birthday')->nullable();
            $table->string('karte_number', 4)->nullable();
            $table->string('gender', 10)->nullable();
            $table->text('memo')->nullable();
            $table->string('line_id', 100)->nullable();
            $table->string('external_id', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
