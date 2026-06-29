<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel-tabel pendukung profil tutor: pendidikan, pengalaman,
     * sertifikat, jadwal ketersediaan, dan materi belajar.
     */
    public function up(): void
    {
        Schema::create('tutor_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('degree');
            $table->string('institution');
            $table->string('major')->nullable();
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('institution')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year_start')->nullable();
            $table->unsignedSmallInteger('year_end')->nullable();
            $table->timestamps();
        });

        Schema::create('tutor_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->string('issued_by')->nullable();
            $table->unsignedSmallInteger('issued_year')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();
        });

        Schema::create('tutor_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->comment('0 = Minggu ... 6 = Sabtu');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tutor_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_materials');
        Schema::dropIfExists('tutor_availabilities');
        Schema::dropIfExists('tutor_certificates');
        Schema::dropIfExists('tutor_experiences');
        Schema::dropIfExists('tutor_educations');
    }
};
