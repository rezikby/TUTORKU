<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorEducation extends Model
{
    protected $table = 'tutor_educations';

    protected $fillable = ['tutor_profile_id', 'degree', 'institution', 'major', 'year_start', 'year_end'];

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
