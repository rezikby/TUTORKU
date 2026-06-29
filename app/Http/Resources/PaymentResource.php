<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'gateway' => $this->gateway,
            'method' => $this->method,
            'amount' => $this->amount,
            'status' => $this->status,
            'payment_url' => $this->payment_url,
            'paid_at' => $this->paid_at,
        ];
    }
}
