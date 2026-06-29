<?php

/**
 * FILE: backend/app/Http/Controllers/Api/PaymentController.php
 * STATUS: DIUBAH (status mapping baru)
 */


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\Payment;
use App\Notifications\BookingStatusNotification;
use App\Notifications\PaymentStatusNotification;
use Illuminate\Support\Facades\Notification;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->id === $payment->user_id, 403);

        return new PaymentResource($payment);
    }

    /** Webhook resmi dari Midtrans. URL ini didaftarkan di dashboard Midtrans. */
    public function midtransWebhook(Request $request)
    {
        return $this->processCallback('midtrans', $request->all());
    }

    /** Webhook (callback) resmi dari Xendit. */
    public function xenditWebhook(Request $request)
    {
        if ($request->header('x-callback-token') !== config('services.xendit.callback_token')) {
            return response()->json(['message' => 'Invalid callback token.'], 403);
        }

        return $this->processCallback('xendit', $request->all());
    }

    protected function processCallback(string $gatewayName, array $payload)
    {
        $gateway = PaymentGatewayFactory::make($gatewayName);
        $result = $gateway->handleCallback($payload);

        $payment = Payment::where('invoice_number', $result['reference'])
            ->orWhere('gateway_reference', $result['reference'])
            ->first();

        if (! $payment) {
            Log::warning("Payment tidak ditemukan untuk callback {$gatewayName}", $result);

            return response()->json(['message' => 'Payment not found'], 404);
        }

        $this->applyPaymentResult($payment, $result['status'], $result['raw']);

        return response()->json(['message' => 'OK']);
    }

    /**
     * Endpoint simulasi pembayaran untuk environment lokal/sandbox
     * (dipakai saat MIDTRANS_SERVER_KEY / XENDIT_SECRET_KEY belum diisi).
     */
    public function simulate(Request $request, Payment $payment)
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $validated = $request->validate([
            'status' => ['required', 'in:paid,success,failed,expired,cancelled'],
        ]);

        $this->applyPaymentResult($payment, in_array($validated['status'], ['paid', 'success'], true) ? 'paid' : $validated['status'], ['simulated' => true]);

        return new PaymentResource($payment->fresh());
    }

    /**
     * Cek status pembayaran terbaru dari gateway, lalu sinkronkan ke database.
     * Dipanggil dari frontend ketika webhook tidak diterima (misal dev lokal / sandbox).
     */
    public function checkStatus(Request $request, Payment $payment)
    {
        $user = $request->user();

        // Load relasi dulu agar tidak null saat cek otorisasi
        $payment->loadMissing(['booking.tutorProfile']);

        abort_unless(
            $user->isAdmin()
                || $user->id === $payment->user_id
                || $user->id === optional($payment->booking?->tutorProfile)->user_id,
            403
        );

        // Jika sudah final, tidak perlu cek ulang ke gateway
        if (in_array($payment->status, ['paid', 'failed', 'expired', 'cancelled'], true)) {
            return new PaymentResource($payment);
        }

        try {
            $gateway = PaymentGatewayFactory::make($payment->gateway);

            if (method_exists($gateway, 'checkTransactionStatus')) {
                $result = $gateway->checkTransactionStatus($payment);
                if ($result && $result['status'] !== 'pending') {
                    $this->applyPaymentResult($payment, $result['status'], $result['raw']);
                    $payment->refresh();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Payment status check failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return new PaymentResource($payment->fresh());
    }

    /**
     * Terapkan hasil pembayaran dari gateway ke database.
     * Mengupdate status payment & booking, membuat live session jika berhasil,
     * lalu kirim notifikasi.
     */
    protected function applyPaymentResult(Payment $payment, string $status, array $raw): void
    {
        // Idempoten — jangan proses ulang jika sudah paid
        if ($payment->isSuccessful()) {
            return;
        }

        $normalizedStatus = in_array($status, ['paid', 'success'], true) ? 'paid' : $status;

        // Jalankan update di dalam transaksi database
        DB::transaction(function () use ($payment, $normalizedStatus, $raw) {
            $payment->update([
                'status'      => $normalizedStatus,
                'raw_payload' => $raw,
                'paid_at'     => $normalizedStatus === 'paid' ? now() : null,
            ]);

            // Refresh agar $payment->booking tidak pakai cache lama
            $payment->refresh();
            $booking = $payment->booking;

            if (! $booking) {
                Log::error('applyPaymentResult: booking tidak ditemukan', ['payment_id' => $payment->id]);
                return;
            }

            if ($normalizedStatus === 'paid') {
                // Cek konflik slot — jangan konfirmasi jika slot sudah diambil booking lain
                $conflict = Booking::where('tutor_profile_id', $booking->tutor_profile_id)
                    ->where('date', $booking->date)
                    ->where('start_time', $booking->start_time)
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->where('id', '<>', $booking->id)
                    ->exists();

                if ($conflict) {
                    Log::warning('Payment received but slot already taken', ['booking_id' => $booking->id]);
                    $booking->update(['status' => 'cancelled']);
                    $payment->update(['status' => 'failed']);
                } else {
                    $booking->update(['status' => 'confirmed']);

                    LiveSession::firstOrCreate(
                        ['booking_id' => $booking->id],
                        ['room_id' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'scheduled']
                    );
                }
            } elseif (in_array($normalizedStatus, ['failed', 'expired', 'cancelled'], true)) {
                $booking->update(['status' => 'cancelled']);
            }
        });

        // Refresh relasi sesudah transaksi agar status terbaru yang dipakai untuk notifikasi
        $payment->refresh();
        $payment->load(['user', 'booking.tutorProfile.user', 'booking.student']);

        // Kirim notifikasi ke pemilik payment
        if ($payment->user) {
            Notification::sendNow($payment->user, new PaymentStatusNotification($payment));
        }

        // Kirim notifikasi booking confirmed ke tutor dan siswa
        $booking = $payment->booking;
        if ($normalizedStatus === 'paid' && $booking && $booking->status === 'confirmed') {
            if ($booking->tutorProfile?->user) {
                Notification::sendNow($booking->tutorProfile->user, new BookingStatusNotification($booking, 'confirmed'));
            }
            if ($booking->student) {
                Notification::sendNow($booking->student, new BookingStatusNotification($booking, 'confirmed'));
            }
        }
    }
}
