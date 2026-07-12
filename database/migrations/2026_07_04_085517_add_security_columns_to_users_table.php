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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password'); // admin বা user আলাদা করার জন্য
            $table->string('register_ip')->nullable()->after('role');
            $table->string('register_device_id')->nullable()->after('register_ip');
            $table->string('last_login_ip')->nullable()->after('register_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'register_ip', 'register_device_id', 'last_login_ip']);
        });
    }
};