<?php
/**
 * FILE: backend/app/Services/Payment/XenditService.php
 * STATUS: DIUBAH (mapping method pembayaran lengkap)
 */


namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi Xendit Invoice API (https://developers.xendit.co/api-reference/#create-invoice).
 * Cukup REST API + secret key (Basic Auth), tanpa SDK tambahan.
 */
class XenditService implements PaymentGatewayInterface
{
    protected string $secretKey;

    public function __construct()
    {
        $this->secretKey = (string) config('services.xendit.secret_key');
    }

    public function createTransaction(Payment $payment): array
    {
        $booking = $payment->booking()->with('student')->first();

        $response = Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $payment->invoice_number,
                'amount' => $payment->amount,
                'payer_email' => $booking?->student?->email,
                'description' => 'Pembayaran sesi belajar TUTORKU #'.$payment->invoice_number,
                'currency' => 'IDR',
                'payment_methods' => $this->mapMethodToXendit($payment->method),
            ]);

        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::warning('Xendit invoice creation failed', $data);
            throw new \RuntimeException(
                $data['message'] ?? 'Gagal membuat invoice Xendit. Silakan coba lagi.'
            );
        }

        // Validasi: invoice_url harus ada di response yang success
        if (empty($data['invoice_url'])) {
            Log::warning('Xendit response missing invoice_url', ['response' => $data]);
            throw new \RuntimeException('Payment URL tidak ditemukan. Silakan hubungi support.');
        }

        return [
            'payment_url' => $data['invoice_url'],
            'reference' => $data['id'] ?? $payment->invoice_number,
            'raw' => $data,
        ];
    }

    public function handleCallback(array $payload): array
    {
        $status = match (strtoupper($payload['status'] ?? '')) {
            'PAID', 'SETTLED' => 'paid',
            'PENDING' => 'pending',
            'EXPIRED' => 'expired',
            default => 'failed',
        };

        return [
            'reference' => $payload['external_id'] ?? null,
            'status' => $status,
            'raw' => $payload,
        ];
    }

    protected function mapMethodToXendit(?string $method): array
    {
        return match ($method) {
            'qris' => ['QRIS'],
            'ovo' => ['OVO'],
            'dana' => ['DANA'],
            'gopay' => ['QRIS'],
            'shopeepay' => ['SHOPEEPAY'],
            'bank_transfer', 'virtual_account' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
            'cod' => [],
            default => ['QRIS', 'BCA', 'OVO'],
        };
    }
}
