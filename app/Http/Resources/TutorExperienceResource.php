<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorExperienceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'institution' => $this->institution,
            'description' => $this->description,
            'year_start' => $this->year_start,
            'year_end' => $this->year_end,
        ];
    }
}
