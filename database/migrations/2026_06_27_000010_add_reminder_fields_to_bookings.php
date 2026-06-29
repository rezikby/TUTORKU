<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->datetime('reminder_sent_at')->nullable()->after('is_hidden');
            $table->boolean('reminder_sent_email')->default(false)->after('reminder_sent_at');
            $table->boolean('reminder_sent_whatsapp')->default(false)->after('reminder_sent_email');
            $table->boolean('reminder_sent_push')->default(false)->after('reminder_sent_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['reminder_sent_at', 'reminder_sent_email', 'reminder_sent_whatsapp', 'reminder_sent_push']);
        });
    }
};
