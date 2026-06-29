<?php

namespace App\Notifications;

use App\Models\ForumComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ForumReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ForumComment $comment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'forum',
            'title' => $this->comment->user->name.' membalas diskusimu',
            'message' => \Illuminate\Support\Str::limit($this->comment->body, 80),
            'action_url' => '/forum/'.$this->comment->forum_post_id,
            'forum_post_id' => $this->comment->forum_post_id,
        ];
    }
}
