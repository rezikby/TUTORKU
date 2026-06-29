<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'likes' => $this->likes_count,
            'is_solution' => $this->is_solution,
            'time' => $this->created_at?->diffForHumans(),
            'replies' => ForumCommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
