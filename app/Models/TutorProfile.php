<?php
/**
 * FILE: backend/app/Models/TutorProfile.php
 * STATUS: DIUBAH (tambah banyak kolom: province, profile_photo_path, ktp, selfie, like/dislike, view_count, relasi baru)
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorProfile extends Model
{
    protected $table = 'tutor_profiles';

    protected $fillable = [
        'user_id', 'headline', 'bio', 'price_per_hour', 'experience_years',
        'city', 'province', 'address', 'latitude', 'longitude', 'google_maps_url', 'levels',
        'mode_online', 'mode_offline', 'badge', 'verification_status',
        'verification_note', 'registration_step', 'registration_submitted',
        'profile_photo_path', 'intro_video_url', 'intro_video_path',
        'identity_document_path', 'ktp_photo_path', 'selfie_ktp_path', 'cv_path',
        'rating_avg', 'rating_count', 'like_count', 'dislike_count', 'view_count',
        'total_students', 'total_sessions', 'balance',
        'bank_name', 'bank_account_number', 'bank_account_holder',
    ];

    protected function casts(): array
    {
        return [
            'levels' => 'array',
            'mode_online' => 'boolean',
            'mode_offline' => 'boolean',
            'registration_submitted' => 'boolean',
            'rating_avg' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'tutor_subject');
    }

    public function educations(): HasMany
    {
        return $this->hasMany(TutorEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(TutorExperience::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TutorCertificate::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TutorAvailability::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(TutorMaterial::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favoritedBy(): HasMany
    {
        return $this->hasMany(TutorFavorite::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(TutorLike::class);
    }

    public function reports(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/'.$this->profile_photo_path) : $this->user?->avatar_url;
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }
}
