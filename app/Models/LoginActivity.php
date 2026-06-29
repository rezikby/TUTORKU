<?php
/**
 * FILE: backend/app/Models/LoginActivity.php
 * STATUS: BARU
 */


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginActivity extends Model
{
    protected $table = 'login_activities';

    protected $fillable = [
        'user_id', 'token_id', 'method', 'device_name', 'platform', 'browser',
        'ip_address', 'user_agent', 'last_active_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at);
    }
}
