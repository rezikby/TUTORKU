<?php
/**
 * FILE: backend/app/Services/Payment/MidtransService.php
 * STATUS: DIUBAH (mapping method pembayaran lengkap + fix error handling)
 */


namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi Midtrans Snap API (https://docs.midtrans.com/docs/snap-snap-integration-guide).
 * Tidak butuh SDK tambahan, cukup REST API + server key (Basic Auth).
 */
class MidtransService implements PaymentGatewayInterface
{
    protected string $serverKey;

    protected bool $isProduction;

    public function __construct()
    {
        $this->serverKey = (string) config('services.midtrans.server_key');
        $this->isProduction = (bool) config('services.midtrans.is_production');
    }

    protected function snapUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }

    public function createTransaction(Payment $payment): array
    {
        $booking = $payment->booking()->with(['student', 'tutorProfile.user'])->first();

        // Catatan: Midtrans Snap API deprecated 'enabled_payments' parameter.
        // User harus pilih payment method LANGSUNG DI MIDTRANS UI (lebih fleksibel & reliable).
        // Backend hanya save method selection untuk reference/analytics, tidak kirim ke Midtrans.
        $response = Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->post($this->snapUrl(), [
                'transaction_details' => [
                    'order_id' => $payment->invoice_number,
                    'gross_amount' => $payment->amount,
                ],
                'customer_details' => [
                    'first_name' => $booking?->student?->name,
                    'email' => $booking?->student?->email,
                    'phone' => $booking?->student?->phone,
                ],
                // Redirect user back to homepage after checkout. Include booking/payment ids
                // so frontend can optionally show a message or fetch booking status.
                'callbacks' => [
                    'finish' => config('app.frontend_url')."/?booking_id={$booking?->id}&payment_id={$payment->id}",
                ],
            ]);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::warning('Midtrans transaction creation failed', $data);

            throw new \RuntimeException(
                $data['error_messages'][0] ?? 'Gagal membuat transaksi pembayaran. Silakan coba lagi.'
            );
        }

        // Validasi: redirect_url harus ada di response yang success
        if (empty($data['redirect_url'])) {
            Log::warning('Midtrans response missing redirect_url', ['response' => $data]);
            throw new \RuntimeException('Payment URL tidak ditemukan. Silakan hubungi support.');
        }

        return [
            'payment_url' => $data['redirect_url'],
            'reference' => $data['token'] ?? $payment->invoice_number,
            'raw' => $data,
        ];
    }

    public function handleCallback(array $payload): array
    {
        $status = match ($payload['transaction_status'] ?? null) {
            'capture', 'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'failure' => 'failed',
            'cancel', 'expire' => 'expired',
            default => 'pending',
        };

        return [
            'reference' => $payload['order_id'] ?? null,
            'status' => $status,
            'raw' => $payload,
        ];
    }

    /**
     * Cek status transaksi aktif dari Midtrans Status API.
     * Dipakai ketika webhook tidak diterima (dev lokal / sandbox).
     */
    public function checkTransactionStatus(Payment $payment): ?array
    {
        if (empty($payment->gateway_reference) && empty($payment->invoice_number)) {
            return null;
        }

        $orderId = $payment->invoice_number;
        $baseUrl = $this->isProduction
            ? "https://api.midtrans.com/v2/{$orderId}/status"
            : "https://api.sandbox.midtrans.com/v2/{$orderId}/status";

        $response = Http::withBasicAuth($this->serverKey, '')->acceptJson()->get($baseUrl);

        if ($response->failed()) {
            return null;
        }

        $data = $response->json() ?? [];

        $status = match ($data['transaction_status'] ?? null) {
            'capture', 'settlement' => 'paid',
            'pending' => 'pending',
            'deny', 'failure' => 'failed',
            'cancel', 'expire' => 'expired',
            default => null,
        };

        if ($status === null) {
            return null;
        }

        return ['status' => $status, 'raw' => $data];
    }

    // Catatan: Method ini tidak dipakai lagi karena enabled_payments parameter deprecated di Midtrans Snap API.
    // Disimpan untuk reference saja. User pilih payment method langsung di Midtrans UI.
    protected function mapMethodToMidtrans(?string $method): array
    {
        return match ($method) {
            'qris' => ['other_qris'],
            'gopay' => ['gopay'],
            'ovo' => ['other_qris'], // Midtrans Snap mengarahkan OVO lewat QRIS/linked account, tidak ada channel terpisah.
            'dana' => ['other_qris'],
            'shopeepay' => ['shopeepay'],
            'bank_transfer', 'virtual_account' => ['bca_va', 'bni_va', 'bri_va', 'permata_va', 'other_va'],
            'cod' => [], // COD tidak melalui Midtrans, ditangani langsung di BookingController.
            default => ['gopay', 'shopeepay', 'other_qris', 'bca_va', 'bni_va', 'bri_va', 'other_va'],
        };
    }
}