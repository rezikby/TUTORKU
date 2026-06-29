<?php
/**
 * FILE: backend/app/Models/Withdrawal.php
 * STATUS: BARU
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'tutor_profile_id', 'amount', 'bank_name', 'bank_account_number',
        'bank_account_holder', 'status', 'admin_note', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function tutorProfile(): BelongsTo
    {
        return $this->belongsTo(TutorProfile::class);
    }
}
