<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_materials', function (Blueprint $table) {
            $table->boolean('comments_enabled')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('tutor_materials', function (Blueprint $table) {
            $table->dropColumn('comments_enabled');
        });
    }
};
