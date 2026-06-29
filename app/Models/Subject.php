<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $fillable = ['name', 'slug', 'icon'];

    public function tutorProfiles(): BelongsToMany
    {
        return $this->belongsToMany(TutorProfile::class, 'tutor_subject');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function studyLogs(): HasMany
    {
        return $this->hasMany(StudyLog::class);
    }
}
