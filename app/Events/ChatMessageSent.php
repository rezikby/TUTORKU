<?php

namespace App\Events;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.'.$this->message->conversation_id),
        ];

        // Also broadcast to recipient's global chat channel for notifications outside chat room
        try {
            $conversation = $this->message->conversation;
            if ($conversation) {
                $recipientId = null;
                if ($conversation->user_one_id === $this->message->sender_id) {
                    $recipientId = $conversation->user_two_id;
                } elseif ($conversation->user_two_id === $this->message->sender_id) {
                    $recipientId = $conversation->user_one_id;
                }

                if ($recipientId && $recipientId !== $this->message->sender_id) {
                    $channels[] = new PrivateChannel('chat-messages.'.$recipientId);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get recipient for chat notification', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $data = [
            'message' => (new ChatMessageResource($this->message->loadMissing('sender')))->resolve(),
        ];

        // Add sender info for notification display
        if ($this->message->sender) {
            $data['sender_name'] = $this->message->sender->name;
            $data['sender_avatar'] = $this->message->sender->avatar;
        }

        return $data;
    }
}

