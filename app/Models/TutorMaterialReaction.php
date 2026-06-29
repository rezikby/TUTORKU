<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorMaterialReaction extends Model
{
    protected $table = 'tutor_material_reactions';

    protected $fillable = [
        'tutor_material_id',
        'user_id',
        'type',
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
