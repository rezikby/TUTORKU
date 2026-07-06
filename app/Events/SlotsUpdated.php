<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class SlotsUpdated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public int $tutor_profile_id;
    public string $date;

    /**
     * Create a new event instance.
     */
    public function __construct(int $tutor_profile_id, string $date)
    {
        $this->tutor_profile_id = $tutor_profile_id;
        $this->date = $date;
    }

    /**
     * The data to broadcast.
     * Return a simple JSON payload matching frontend expectation.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'slots.updated',
            'tutor_profile_id' => $this->tutor_profile_id,
            'date' => $this->date,
        ];
    }

    /**
     * Channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        // broadcast on a public channel; frontend clients can listen globally
        return new Channel('slots');
    }
}
