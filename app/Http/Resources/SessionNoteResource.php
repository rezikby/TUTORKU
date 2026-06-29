<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'generated_summary' => $this->generated_summary,
            'progress_notes' => $this->progress_notes,
            'tasks' => $this->tasks,
            'created_at' => $this->created_at,
        ];
    }
}
