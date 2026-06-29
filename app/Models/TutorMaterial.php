<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorMaterial extends Model
{
    protected $table = 'tutor_materials';

    protected $fillable = [
        'tutor_profile_id',
        'subject_id',
        'title',
        'description',
        'comments_enabled',
        'file_path',
        'thumbnail_path',
        'views_count',
        'likes_count',
        'dislikes_count',
        'comments_count',
    ];

    protected $casts = [
        'comments_enabled' => 'boolean',
    ];

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TutorMaterialComment::class, 'tutor_material_id')->latest();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(TutorMaterialReaction::class, 'tutor_material_id');
    }
}
