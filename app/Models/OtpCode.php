<?php
/**
 * FILE: backend/app/Models/OtpCode.php
 * STATUS: BARU
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $table = 'otp_codes';

    protected $fillable = [
        'identifier', 'purpose', 'code', 'attempts', 'expires_at', 'verified_at', 'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return ! is_null($this->verified_at);
    }

    public function maxAttemptsReached(): bool
    {
        return $this->attempts >= (int) config('services.otp.max_attempts', 5);
    }
}
