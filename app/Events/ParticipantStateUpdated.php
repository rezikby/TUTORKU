<?php

namespace App\Events;

use App\Models\LiveSessionParticipant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParticipantStateUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int $userId,
        public array $participantData,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("live-session.{$this->roomId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'participant.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'data' => $this->participantData,
        ];
    }
}
