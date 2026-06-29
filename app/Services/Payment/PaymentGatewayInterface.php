<?php

namespace App\Services\Payment;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    /**
     * Membuat transaksi pembayaran di payment gateway dan mengembalikan
     * array berisi ['payment_url' => string|null, 'reference' => string, 'raw' => array].
     */
    public function createTransaction(Payment $payment): array;

    /**
     * Memverifikasi & mem-parsing payload webhook/callback dari gateway.
     * Mengembalikan ['reference' => string, 'status' => 'paid'|'pending'|'failed'|'expired', 'raw' => array].
     */
    public function handleCallback(array $payload): array;
}
