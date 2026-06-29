<?php
/**
 * FILE: backend/app/Http/Resources/ChatMessageResource.php
 * STATUS: DIUBAH (hapus field is_mine yang menyesatkan)
 */


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'content' => $this->content,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'file_name' => $this->file_name,
            'duration_seconds' => $this->duration_seconds,
            'read_at' => $this->read_at,
            'is_deleted' => $this->is_deleted,
            'deleted_for' => $this->deleted_for,
            'deleted_by_user_id' => $this->deleted_by_user_id,
            'created_at' => $this->created_at,
        ];
    }
}
