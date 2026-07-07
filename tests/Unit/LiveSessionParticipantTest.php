<?php

namespace Tests\Unit;

use App\Models\LiveSessionParticipant;
use App\Models\User;
use Tests\TestCase;

class LiveSessionParticipantTest extends TestCase
{
    public function test_to_participant_presence_includes_pretest_fields(): void
    {
        $participant = new LiveSessionParticipant([
            'id' => 7,
            'live_session_id' => 1,
            'user_id' => 42,
            'is_audio_on' => true,
            'is_video_on' => true,
            'is_screen_sharing' => false,
            'is_speaking' => false,
            'pretest_completed' => true,
            'pretest_score' => 8,
            'pretest_total_questions' => 10,
        ]);

        $user = new User();
        $user->id = 42;
        $user->name = 'Student';
        $user->avatar_url = '/avatar.png';
        $user->role = 'student';

        $participant->setRelation('user', $user);

        $presence = $participant->toParticipantPresence();

        $this->assertTrue($presence['pretestCompleted']);
        $this->assertSame(8, $presence['pretestScore']);
        $this->assertSame(10, $presence['pretestTotalQuestions']);
    }
}
