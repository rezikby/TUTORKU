<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveSessionParticipant extends Model
{
    use HasFactory;

    protected $table = 'live_session_participants';

    protected $fillable = [
        'live_session_id',
        'user_id',
        'is_audio_on',
        'is_video_on',
        'is_screen_sharing',
        'is_speaking',
    ];

    protected $casts = [
        'is_audio_on' => 'boolean',
        'is_video_on' => 'boolean',
        'is_screen_sharing' => 'boolean',
        'is_speaking' => 'boolean',
    ];

    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toParticipantPresence(): array
    {
        return [
            'id' => $this->user_id,
            'name' => $this->user->name,
            'avatar' => $this->user->avatar_url,
            'role' => $this->user->role,
            'isAudioOn' => $this->is_audio_on,
            'isVideoOn' => $this->is_video_on,
            'isScreenSharing' => $this->is_screen_sharing,
            'isSpeaking' => $this->is_speaking,
        ];
    }
}
