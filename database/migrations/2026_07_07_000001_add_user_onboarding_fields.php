<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('education_level')->nullable()->after('city');
            $table->boolean('onboarding_completed')->default(false)->after('education_level');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding_completed')) {
                $table->dropColumn('onboarding_completed');
            }
            if (Schema::hasColumn('users', 'education_level')) {
                $table->dropColumn('education_level');
            }
        });
    }
};
