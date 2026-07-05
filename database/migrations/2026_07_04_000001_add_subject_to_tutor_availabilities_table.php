<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_availabilities', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('tutor_profile_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tutor_availabilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};
