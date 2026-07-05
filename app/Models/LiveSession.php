<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LiveSession extends Model
{
    protected $table = 'live_sessions';

    protected $fillable = [
        'booking_id', 'room_id', 'status', 'started_at', 'ended_at',
        'paused_at', 'total_paused_seconds', 'duration_seconds', 'whiteboard_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'paused_at' => 'datetime',
            'whiteboard_snapshot' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function note(): HasOne
    {
        return $this->hasOne(SessionNote::class);
    }
}
