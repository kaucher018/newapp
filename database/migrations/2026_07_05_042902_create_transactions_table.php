<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['deposit', 'withdraw', 'game_play', 'bonus']);
            $table->decimal('amount', 15, 2);
            $table->string('sender_number')->nullable(); // ইউজার বিকাশ নম্বর বা গেম স্লাগ
            $table->string('receiver_number')->nullable(); // উইথড্রর বিকাশ নম্বর
            $table->string('admin_bkash_number')->nullable(); // এডমিনের কোন নম্বরে পাঠিয়েছে
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};