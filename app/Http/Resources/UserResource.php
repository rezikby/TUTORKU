<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email_display,
            'role' => $this->role,
            'phone' => $this->phone,
            'avatar' => $this->avatar_url,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'address' => $this->address,
            'city' => $this->city,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at,
            'settings' => new UserSettingResource($this->whenLoaded('settings')),
            'tutor_profile' => new TutorProfileResource($this->whenLoaded('tutorProfile')),
            'created_at' => $this->created_at,
        ];
    }
}
