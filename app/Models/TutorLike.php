<?php
/**
 * FILE: backend/app/Models/TutorLike.php
 * STATUS: BARU
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorLike extends Model
{
    protected $table = 'tutor_likes';

    protected $fillable = ['tutor_profile_id', 'user_id', 'type'];

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
