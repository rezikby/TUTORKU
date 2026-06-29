<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Notifications\Notification;

class NewChatMessageNotification extends Notification
{

    public function __construct(public ChatMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'chat',
            'title' => 'Pesan baru dari '.$this->message->sender->name,
            'message' => $this->message->type === 'text' ? \Illuminate\Support\Str::limit($this->message->content, 80) : ucfirst($this->message->type).' baru',
            'action_url' => '/chat/'.$this->message->conversation_id,
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
