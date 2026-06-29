<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_materials', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('thumbnail_path');
            $table->unsignedInteger('likes_count')->default(0)->after('views_count');
            $table->unsignedInteger('dislikes_count')->default(0)->after('likes_count');
            $table->unsignedInteger('comments_count')->default(0)->after('dislikes_count');
        });

        Schema::create('tutor_material_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_material_id')->constrained('tutor_materials')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('tutor_material_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_material_id')->constrained('tutor_materials')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->timestamps();
            $table->unique(['tutor_material_id', 'user_id'], 'material_user_reaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_material_reactions');
        Schema::dropIfExists('tutor_material_comments');

        Schema::table('tutor_materials', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'likes_count', 'dislikes_count', 'comments_count']);
        });
    }
};
