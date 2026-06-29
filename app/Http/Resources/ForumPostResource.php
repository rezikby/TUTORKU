<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ForumPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => $this->whenLoaded('category', fn () => $this->category->name),
            'title' => $this->title,
            'body' => $this->body,
            'likes' => $this->likes_count,
            'replies' => $this->comments_count,
            'views' => $this->views_count,
            'solved' => $this->solved,
            'liked_by_me' => $this->when(
                $request->user() && $this->relationLoaded('likes'),
                fn () => $this->likes->contains('user_id', $request->user()?->id)
            ),
            'bookmarked_by_me' => $this->when(
                $request->user() && $this->relationLoaded('bookmarks'),
                fn () => $this->bookmarks->contains('user_id', $request->user()?->id)
            ),
            'time' => $this->created_at?->diffForHumans(),
            'created_at' => $this->created_at,
            'comments' => ForumCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
