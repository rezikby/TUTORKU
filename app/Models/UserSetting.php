<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $table = 'user_settings';

    protected $fillable = [
        'user_id', 'language', 'dark_mode', 'notif_email', 'notif_whatsapp', 'notif_push', 'reminder_time',
    ];

    protected function casts(): array
    {
        return [
            'dark_mode' => 'boolean',
            'notif_email' => 'boolean',
            'notif_whatsapp' => 'boolean',
            'notif_push' => 'boolean',
            'reminder_time' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
