<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorCertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'file_url' => $this->file_path ? asset('storage/'.$this->file_path) : null,
            'issued_by' => $this->issued_by,
            'issued_year' => $this->issued_year,
            'verified' => $this->verified,
        ];
    }
}
