<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorCertificate extends Model
{
    protected $table = 'tutor_certificates';

    protected $fillable = ['tutor_profile_id', 'name', 'file_path', 'issued_by', 'issued_year', 'verified'];

    protected function casts(): array
    {
        return ['verified' => 'boolean'];
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
