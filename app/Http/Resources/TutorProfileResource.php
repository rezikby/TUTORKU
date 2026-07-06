<?php
/**
 * FILE: backend/app/Http/Resources/TutorProfileResource.php
 * STATUS: DIUBAH (admin bisa lihat dokumen verifikasi, tambah field baru)
 */


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        $isOwner = $request->user()?->id === $this->user_id;
        $isAdmin = $request->user()?->role === 'admin';
        $canSeePrivateData = $isOwner || $isAdmin;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user->name,
            'photo' => $this->profile_photo_url,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'subjects' => SubjectResource::collection($this->whenLoaded('subjects')),
            'subject_label' => $this->whenLoaded('subjects', fn () => $this->subjects->pluck('name')->join(' & ')),
            'price_per_hour' => $this->price_per_hour,
            'experience_years' => $this->experience_years,
            'experience_label' => $this->experience_years.' tahun',
            'city' => $this->city,
            'province' => $this->province,
            'location' => trim(collect([$this->city, $this->province])->filter()->join(', ')),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'google_maps_url' => $this->google_maps_url,
            'levels' => $this->levels,
            'level_label' => $this->levels ? implode(' / ', $this->levels) : null,
            'mode_online' => $this->mode_online,
            'mode_offline' => $this->mode_offline,
            'online' => $this->mode_online,
            'badge' => $this->badge,
            'email' => $this->user->email,
            'phone' => $this->when($canSeePrivateData, $this->user->phone),
            'verified' => $this->verification_status === 'verified',
            'verification_status' => $this->verification_status,
            'verification_note' => $this->when($canSeePrivateData, $this->verification_note),
            'registration_step' => $this->registration_step,
            'registration_submitted' => $this->registration_submitted,
            'rating' => (float) $this->rating_avg,
            'reviews' => $this->rating_count,
            'like_count' => $this->like_count,
            'dislike_count' => $this->dislike_count,
            'view_count' => $this->view_count,
            'total_students' => $this->total_students,
            'total_sessions' => $this->total_sessions,
            'bookings_count' => $this->bookings_count ?? 0,
            'has_bookings' => ($this->bookings_count ?? 0) > 0,
            'is_booked_by_me' => ($this->my_bookings_count ?? 0) > 0,
            'intro_video_url' => $this->intro_video_url,
            'intro_video_path' => $this->intro_video_path ? asset('storage/'.$this->intro_video_path) : null,
            'balance' => $this->when($canSeePrivateData, $this->balance),
            'bank_name' => $this->when($canSeePrivateData, $this->bank_name),
            'bank_account_number' => $this->when($canSeePrivateData, $this->bank_account_number),
            'bank_account_holder' => $this->when($canSeePrivateData, $this->bank_account_holder),
            'ktp_photo' => $this->when($canSeePrivateData, $this->ktp_photo_path ? asset('storage/'.$this->ktp_photo_path) : null),
            'selfie_ktp' => $this->when($canSeePrivateData, $this->selfie_ktp_path ? asset('storage/'.$this->selfie_ktp_path) : null),
            'cv' => $this->when($canSeePrivateData, $this->cv_path ? asset('storage/'.$this->cv_path) : null),
            'educations' => TutorEducationResource::collection($this->whenLoaded('educations')),
            'experiences' => TutorExperienceResource::collection($this->whenLoaded('experiences')),
            'certificates' => TutorCertificateResource::collection($this->whenLoaded('certificates')),
            'availabilities' => TutorAvailabilityResource::collection($this->whenLoaded('availabilities')),
        ];
    }
}
