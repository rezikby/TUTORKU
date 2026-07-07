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
        'pretest_completed',
        'pretest_score',
        'pretest_total_questions',
        'posttest_completed',
        'posttest_score',
        'posttest_total_questions',
    ];

    protected $casts = [
        'is_audio_on' => 'boolean',
        'is_video_on' => 'boolean',
        'is_screen_sharing' => 'boolean',
        'is_speaking' => 'boolean',
        'pretest_completed' => 'boolean',
        'pretest_score' => 'integer',
        'pretest_total_questions' => 'integer',
        'posttest_completed' => 'boolean',
        'posttest_score' => 'integer',
        'posttest_total_questions' => 'integer',
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
            'pretestCompleted' => (bool) $this->pretest_completed,
            'pretestScore' => $this->pretest_score,
            'pretestTotalQuestions' => $this->pretest_total_questions,
            'posttestCompleted' => (bool) $this->posttest_completed,
            'posttestScore' => $this->posttest_score,
            'posttestTotalQuestions' => $this->posttest_total_questions,
        ];
    }
}
