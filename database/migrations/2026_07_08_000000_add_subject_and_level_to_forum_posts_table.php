<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('forum_category_id')->constrained('subjects')->nullOnDelete();
            $table->string('education_level')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subject_id');
            $table->dropColumn('education_level');
        });
    }
};
