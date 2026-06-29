<?php
/**
 * FILE: backend/app/Models/Booking.php
 * STATUS: DIUBAH (tambah kolom lokasi: location_city, location_province, location_latitude/longitude, location_note)
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $table = 'bookings';

    protected $fillable = [
        'code', 'student_id', 'tutor_profile_id', 'subject_id', 'date', 'start_time',
        'duration_minutes', 'mode', 'location_address', 'location_city', 'location_province',
        'location_latitude', 'location_longitude', 'location_note',
        'price', 'service_fee', 'total_price', 'status', 'cancel_reason', 'notes', 'is_hidden',
        'reminder_sent_at', 'reminder_sent_email', 'reminder_sent_whatsapp', 'reminder_sent_push',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'reminder_sent_at' => 'datetime',
            'reminder_sent_email' => 'boolean',
            'reminder_sent_whatsapp' => 'boolean',
            'reminder_sent_push' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function liveSession(): HasOne
    {
        return $this->hasOne(LiveSession::class);
    }
}
