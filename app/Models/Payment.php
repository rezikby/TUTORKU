<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';

    protected $fillable = [
        'booking_id', 'user_id', 'invoice_number', 'gateway', 'method', 'amount',
        'status', 'gateway_reference', 'payment_url', 'paid_at', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSuccessful(): bool
    {
        return in_array($this->status, ['paid', 'success'], true);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'waiting_payment', 'processing'], true);
    }
}
