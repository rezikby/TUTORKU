<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes')->default(10);
            $table->enum('mode', ['online', 'offline'])->default('online');
            $table->text('location_address')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('service_fee')->default(0);
            $table->unsignedInteger('total_price')->default(0);
            $table->enum('status', [
                'pending_payment', 'confirmed', 'ongoing', 'completed', 'cancelled', 'expired',
            ])->default('pending_payment')->index();
            $table->string('cancel_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
