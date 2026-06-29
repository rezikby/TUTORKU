<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->enum('method', ['google', 'phone_otp', 'password'])->default('google')->change();
        });
    }

    public function down(): void
    {
        Schema::table('login_activities', function (Blueprint $table) {
            $table->enum('method', ['google', 'phone_otp'])->default('google')->change();
        });
    }
};
