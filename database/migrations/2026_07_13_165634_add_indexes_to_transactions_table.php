<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // একসাথে যে কলামগুলো দিয়ে আমরা সার্চ বা SUM/COUNT করি সেগুলোতে ইনডেক্স দেওয়া হচ্ছে
            $table->index(['user_id', 'type', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type', 'status', 'created_at']);
        });
    }
};
