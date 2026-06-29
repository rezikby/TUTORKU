<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('room_id')->unique();
            $table->enum('status', ['scheduled', 'ongoing', 'ended'])->default('scheduled')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('whiteboard_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('session_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->text('generated_summary')->nullable()->comment('Catatan belajar otomatis');
            $table->text('progress_notes')->nullable();
            $table->json('tasks')->nullable()->comment('Daftar tugas hasil sesi');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_notes');
        Schema::dropIfExists('live_sessions');
    }
};
