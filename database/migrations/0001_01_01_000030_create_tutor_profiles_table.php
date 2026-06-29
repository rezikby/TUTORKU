<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->unsignedInteger('price_per_hour')->default(0);
            $table->unsignedInteger('experience_years')->default(0);
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('levels')->nullable()->comment('Jenjang yang diajar: SD, SMP, SMA, Mahasiswa');
            $table->boolean('mode_online')->default(true);
            $table->boolean('mode_offline')->default(false);
            $table->enum('badge', ['Top Tutor', 'Verified', 'New'])->nullable();
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending')->index();
            $table->text('verification_note')->nullable();
            $table->unsignedTinyInteger('registration_step')->default(1)
                ->comment('1 Akun, 2 Data Diri, 3 Pendidikan, 4 Sertifikat, 5 Verifikasi/Submitted');
            $table->boolean('registration_submitted')->default(false);
            $table->string('intro_video_url')->nullable();
            $table->string('identity_document_path')->nullable();
            $table->string('cv_path')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('total_students')->default(0);
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedBigInteger('balance')->default(0)->comment('Saldo pendapatan tutor (Rupiah)');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_profiles');
    }
};
