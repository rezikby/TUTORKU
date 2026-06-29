<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'room_id' => $this->room_id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'duration_seconds' => $this->duration_seconds,
            'whiteboard_snapshot' => $this->whiteboard_snapshot,
            'note' => new SessionNoteResource($this->whenLoaded('note')),
            'webrtc_stun_server' => config('services.webrtc.stun_server'),
        ];
    }
}
