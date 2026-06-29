<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->whenLoaded('subject', fn () => $this->subject?->name),
            'date' => $this->date?->toDateString(),
            'duration_minutes' => $this->duration_minutes,
            'score' => $this->score,
            'note' => $this->note,
        ];
    }
}
