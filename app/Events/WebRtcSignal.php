<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinyal WebRTC (offer / answer / ice-candidate / hangup) untuk Live Class.
 * Koneksi video sebenarnya peer-to-peer langsung antar browser (gratis, tanpa
 * server media), Laravel Reverb hanya dipakai sebagai jalur signaling.
 */
class WebRtcSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $roomId,
        public int $fromUserId,
        public string $type,
        public array $payload,
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
        return 'webrtc.signal';
    }

    public function broadcastWith(): array
    {
        return [
            'from_user_id' => $this->fromUserId,
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }
}
