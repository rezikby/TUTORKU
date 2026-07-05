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
            'paused_at' => $this->paused_at,
            'total_paused_seconds' => $this->total_paused_seconds,
            'duration_seconds' => $this->duration_seconds,
            'whiteboard_snapshot' => $this->whiteboard_snapshot,
            'note' => $this->when(
                $this->relationLoaded('note') && $this->note,
                new SessionNoteResource($this->note),
            ),
            'webrtc_stun_server' => config('services.webrtc.stun_server'),
            'webrtc_turn_server' => config('services.webrtc.turn_server'),
            'webrtc_turn_username' => config('services.webrtc.turn_username'),
            'webrtc_turn_password' => config('services.webrtc.turn_password'),
        ];
    }
}
// perbaikan