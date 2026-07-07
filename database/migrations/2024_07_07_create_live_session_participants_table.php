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
        Schema::create('live_session_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('is_audio_on')->default(true);
            $table->boolean('is_video_on')->default(true);
            $table->boolean('is_screen_sharing')->default(false);
            $table->boolean('is_speaking')->default(false);
            $table->timestamps();
            
            // Unique: satu user hanya bisa join sekali per session
            $table->unique(['live_session_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_session_participants');
    }
};
