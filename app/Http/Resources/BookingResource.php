<?php
/**
 * FILE: backend/app/Http/Resources/BookingResource.php
 * STATUS: DIUBAH (tambah field lokasi)
 */


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'student' => new UserResource($this->whenLoaded('student')),
            'tutor' => new TutorProfileResource($this->whenLoaded('tutorProfile')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'date' => $this->date?->toDateString(),
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'duration_minutes' => $this->duration_minutes,
            'mode' => $this->mode,
            'location_address' => $this->location_address,
            'location_city' => $this->location_city,
            'location_province' => $this->location_province,
            'location_latitude' => $this->location_latitude,
            'location_longitude' => $this->location_longitude,
            'location_note' => $this->location_note,
            'price' => $this->price,
            'service_fee' => $this->service_fee,
            'total_price' => $this->total_price,
            'status' => $this->status,
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'live_session' => new LiveSessionResource($this->whenLoaded('liveSession')),
            'review' => new ReviewResource($this->whenLoaded('review')),
            'created_at' => $this->created_at,
        ];
    }
}
