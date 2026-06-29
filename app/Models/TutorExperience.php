<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorExperience extends Model
{
    protected $table = 'tutor_experiences';

    protected $fillable = ['tutor_profile_id', 'title', 'institution', 'description', 'year_start', 'year_end'];

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
