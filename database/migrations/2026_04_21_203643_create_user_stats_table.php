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
        Schema::create('user_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->integer('totalXP')->default(0);
            $table->integer('level')->default(1);
            $table->integer('currentStreak')->default(0);
            $table->integer('longestStreak')->default(0);
            $table->integer('totalCardsStudied')->default(0);
            $table->integer('totalCorrect')->default(0);
            $table->integer('totalWrong')->default(0);
            $table->json('categoryStats');
            $table->json('achievements');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('userstats');
    }
};
