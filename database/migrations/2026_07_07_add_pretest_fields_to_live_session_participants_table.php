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
        Schema::table('live_session_participants', function (Blueprint $table) {
            $table->boolean('pretest_completed')->default(false)->after('is_speaking');
            $table->integer('pretest_score')->nullable()->after('pretest_completed');
            $table->integer('pretest_total_questions')->nullable()->after('pretest_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_session_participants', function (Blueprint $table) {
            $table->dropColumn(['pretest_completed', 'pretest_score', 'pretest_total_questions']);
        });
    }
};
