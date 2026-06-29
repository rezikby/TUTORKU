<?php
/**
 * FILE: backend/app/Events/UserTyping.php
 * STATUS: BARU
 */


namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipancarkan saat user sedang mengetik di sebuah percakapan (Typing Indicator).
 * Pakai ShouldBroadcastNow (bukan ShouldBroadcast) supaya tidak masuk queue —
 * sinyal mengetik harus instan, telat beberapa detik karena antrean tidak berguna.
 */
class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $conversationId,
        public int $userId,
        public bool $isTyping,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'is_typing' => $this->isTyping,
        ];
    }
}
