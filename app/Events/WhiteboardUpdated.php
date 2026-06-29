<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhiteboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int $fromUserId,
        public array $action,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('live-session.'.$this->roomId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'whiteboard.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'from_user_id' => $this->fromUserId,
            'action' => $this->action,
        ];
    }
}
