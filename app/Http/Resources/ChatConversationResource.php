<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $otherUser = $this->otherUser($request->user()->id);

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'with_user' => new UserResource($otherUser),
            'last_message' => new ChatMessageResource($this->whenLoaded('latestMessage')),
            'unread_count' => $this->when(isset($this->unread_count), $this->unread_count),
            'last_message_at' => $this->last_message_at,
        ];
    }
}
