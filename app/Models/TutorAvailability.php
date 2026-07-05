<?php

namespace App\Models;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorAvailability extends Model
{
    protected $table = 'tutor_availabilities';

    protected $fillable = ['tutor_profile_id', 'subject_id', 'date', 'day_of_week', 'start_time', 'end_time', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'date' => 'date',
        ];
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
