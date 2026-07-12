<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_settings', function (Blueprint $table) {
            $table->id();
            $table->string('game_slug')->unique(); // wild_west, aviator, ace_super, slots
            $table->string('game_name');
            $table->enum('algorithm_mode', ['promotion', 'normal', 'admin_profit'])->default('normal');
            $table->integer('normal_admin_percent')->default(60);
            $table->integer('normal_user_percent')->default(40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_settings');
    }
};