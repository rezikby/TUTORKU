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
            $table->boolean('posttest_completed')->default(false)->after('pretest_total_questions');
            $table->integer('posttest_score')->nullable()->after('posttest_completed');
            $table->integer('posttest_total_questions')->nullable()->after('posttest_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_session_participants', function (Blueprint $table) {
            $table->dropColumn(['posttest_completed', 'posttest_score', 'posttest_total_questions']);
        });
    }
};
