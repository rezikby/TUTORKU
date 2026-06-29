<?php

namespace App\Services\Payment;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        $gateway ??= config('services.payment.default_gateway', 'midtrans');

        return match ($gateway) {
            'midtrans' => new MidtransService,
            'xendit' => new XenditService,
            default => throw new InvalidArgumentException("Payment gateway [{$gateway}] tidak dikenali."),
        };
    }
}
