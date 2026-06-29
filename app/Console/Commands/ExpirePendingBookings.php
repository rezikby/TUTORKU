<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire-pending';
    protected $description = 'Batalkan booking pending yang sudah melewati batas waktu pembayaran';

    public function handle(): int
    {
        $timeoutMinutes = (int) config('services.payment.pending_booking_timeout_minutes', 30);
        $cutoff = now()->subMinutes($timeoutMinutes);

        $bookings = Booking::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->with('payment')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('Tidak ada booking pending yang kedaluwarsa.');
            return self::SUCCESS;
        }

        foreach ($bookings as $booking) {
            DB::transaction(function () use ($booking): void {
                $booking->update(['status' => 'cancelled']);

                if ($booking->payment && in_array($booking->payment->status, ['pending', 'processing', 'waiting_payment'], true)) {
                    $booking->payment->update(['status' => 'expired']);
                }

                if ($booking->liveSession) {
                    $booking->liveSession->update(['status' => 'cancelled']);
                }
            });
        }

        $this->info("{$bookings->count()} booking pending berhasil dibatalkan karena kedaluwarsa.");

        return self::SUCCESS;
    }
}
