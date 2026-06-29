<?php

namespace App\Events;

use App\Models\LiveSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public LiveSession $liveSession)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('booking.' . $this->liveSession->booking_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'session.started';
    }

    public function broadcastWith(): array
    {
        return [
            'session' => [
                'id' => $this->liveSession->id,
                'booking_id' => $this->liveSession->booking_id,
                'status' => $this->liveSession->status,
                'started_at' => $this->liveSession->started_at,
            ],
        ];
    }
}
