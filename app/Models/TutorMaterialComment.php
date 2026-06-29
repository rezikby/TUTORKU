<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorMaterialComment extends Model
{
    protected $table = 'tutor_material_comments';

    protected $fillable = [
        'tutor_material_id',
        'user_id',
        'body',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(TutorMaterial::class, 'tutor_material_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
