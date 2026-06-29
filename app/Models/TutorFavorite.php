<?php
/**
 * FILE: backend/app/Models/TutorFavorite.php
 * STATUS: BARU
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorFavorite extends Model
{
    protected $table = 'tutor_favorites';

    protected $fillable = ['user_id', 'tutor_profile_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
