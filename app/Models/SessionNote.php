<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionNote extends Model
{
    protected $table = 'session_notes';

    protected $fillable = [
        'live_session_id', 'generated_summary', 'progress_notes', 'tasks', 'created_by',
    ];

    protected function casts(): array
    {
        return ['tasks' => 'array'];
    }

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
