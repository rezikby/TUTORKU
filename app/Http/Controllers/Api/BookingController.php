<?php
/**
 * FILE: backend/app/Http/Controllers/Api/BookingController.php
 * STATUS: DIUBAH (validasi slot, lokasi, payment error handling)
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingStoreRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\TutorProfile;
use App\Models\LiveSession;
use App\Notifications\BookingStatusNotification;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookingController extends Controller
{

    /**
     * GET /api/bookings — daftar booking milik user yang login
     * (siswa: booking yang dia buat, tutor: booking yang masuk ke dia, admin: semua).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Booking::query()
            ->with(['tutorProfile.user', 'student', 'subject', 'payment', 'liveSession'])
            ->where('is_hidden', false);

        if ($user->isSiswa()) {
            $query->where('student_id', $user->id);
        } elseif ($user->isTutor()) {
            $query->whereHas('tutorProfile', fn ($q) => $q->where('user_id', $user->id));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $bookings = $query->latest('date')->latest('start_time')->paginate($request->integer('per_page', 10));

        return BookingResource::collection($bookings);
    }

    /**
     * POST /api/bookings — buat booking baru sekaligus inisiasi pembayaran.
     *
     * Flow sesuai instruksi:
     * Pilih Tutor -> Pilih Tanggal -> Pilih Jam -> Pilih Metode -> Konfirmasi Booking
     * -> Pembayaran -> Booking Berhasil -> Redirect ke Dashboard (BUKAN Live Class).
     */
    public function store(BookingStoreRequest $request)
    {
        $validated = $request->validated();
        $student = $request->user();

        if (! $student->isSiswa()) {
            return response()->json(['message' => 'Hanya siswa yang dapat membuat booking.'], 403);
        }

        $tutorProfile = TutorProfile::verified()->findOrFail($validated['tutor_profile_id']);

        $price = (int) round($tutorProfile->price_per_hour * ($validated['duration_minutes'] / 60));
        $serviceFee = (int) config('services.payment.service_fee', 2500);

        try {
            $booking = DB::transaction(function () use ($validated, $student, $tutorProfile, $price, $serviceFee) {
                // Slot hanya dianggap terpakai setelah pembayaran berhasil.
                // Booking pending tidak boleh mengunci slot, sehingga banyak siswa dapat
                // membuat booking pending pada slot yang sama sampai satu pembayaran berhasil.
                $slotTaken = Booking::where('tutor_profile_id', $tutorProfile->id)
                    ->where('date', $validated['date'])
                    ->where('start_time', $validated['start_time'])
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->lockForUpdate()
                    ->exists();

                if ($slotTaken) {
                    throw new \RuntimeException('Slot jam ini sudah dibooking. Silakan pilih jam lain.');
                }

                $booking = Booking::create([
                    'code' => 'TRX-'.strtoupper(Str::random(8)),
                    'student_id' => $student->id,
                    'tutor_profile_id' => $tutorProfile->id,
                    'subject_id' => $validated['subject_id'] ?? null,
                    'date' => $validated['date'],
                    'start_time' => $validated['start_time'],
                    'duration_minutes' => $validated['duration_minutes'],
                    'mode' => $validated['mode'],
                    'location_address' => $validated['location_address'] ?? null,
                    'location_city' => $validated['location_city'] ?? null,
                    'location_province' => $validated['location_province'] ?? null,
                    'location_latitude' => $validated['location_latitude'] ?? null,
                    'location_longitude' => $validated['location_longitude'] ?? null,
                    'location_note' => $validated['location_note'] ?? null,
                    'price' => $price,
                    'service_fee' => $serviceFee,
                    'total_price' => $price + $serviceFee,
                    'status' => 'pending', // Pending sampai payment successful dari webhook
                    'notes' => $validated['notes'] ?? null,
                ]);

                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $student->id,
                    'invoice_number' => 'INV-'.strtoupper(Str::random(10)),
                    'gateway' => $validated['gateway'] ?? config('services.payment.default_gateway'),
                    'method' => $validated['method'] ?? null,
                    'amount' => $booking->total_price,
                    'status' => 'pending',
                ]);

                // COD (Cash on Delivery / bayar di tempat) tidak melalui payment gateway online:
                // booking langsung confirmed, pembayaran dicatat 'pending' dan dilunaskan saat sesi berlangsung.
                if ($payment->method === 'cod') {
                    $booking->update(['status' => 'confirmed']);
                    $payment->update(['status' => 'paid']);

                    return $booking;
                }

                $gateway = PaymentGatewayFactory::make($payment->gateway);
                $result = $gateway->createTransaction($payment);

                // Validasi: payment_url harus ada, jika tidak ada berarti error di gateway
                if (empty($result['payment_url'])) {
                    Log::error('Payment URL not generated', [
                        'payment_id' => $payment->id,
                        'gateway' => $payment->gateway,
                        'method' => $payment->method,
                        'result' => $result,
                    ]);
                    throw new \RuntimeException('Gagal mendapatkan link pembayaran dari gateway. Silakan coba lagi.');
                }

                $payment->update([
                    'payment_url' => $result['payment_url'],
                    'gateway_reference' => $result['reference'],
                    'raw_payload' => $result['raw'],
                ]);

                return $booking;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BookingResource($booking->fresh(['tutorProfile.user', 'student', 'subject', 'payment']));
    }

    public function show(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);

        $booking->load(['tutorProfile.user', 'student', 'subject', 'payment', 'liveSession.note', 'review']);

        return new BookingResource($booking);
    }

    /** Tutor mengkonfirmasi booking (umumnya sudah otomatis terkonfirmasi saat pembayaran berhasil; ini untuk kasus manual seperti COD). */
    public function confirm(Request $request, Booking $booking)
    {
        $user = $request->user();

        if (! $user->isTutor() || $booking->tutorProfile->user_id !== $user->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $booking->loadMissing('payment');

        if ($booking->payment && $booking->payment->method !== 'cod' && ! $booking->payment->isSuccessful()) {
            return response()->json([
                'message' => 'Booking ini belum dibayar oleh siswa, belum bisa dikonfirmasi.',
            ], 422);
        }

        if ($booking->status !== 'confirmed') {
            $booking->update(['status' => 'confirmed']);
        }

        $booking->liveSession()->firstOrCreate(
            [],
            ['room_id' => (string) Str::uuid(), 'status' => 'scheduled']
        );

        $booking->student->notify(new BookingStatusNotification($booking, 'confirmed'));

        return new BookingResource($booking->fresh(['tutorProfile.user', 'student', 'subject', 'payment']));
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (in_array($booking->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'Booking ini tidak dapat dibatalkan.'], 422);
        }

        // Allow cancellation only within 5 minutes of creation
        if ($booking->created_at && $booking->created_at->lt(now()->subMinutes(5))) {
            return response()->json(['message' => 'Waktu pembatalan telah lewat (lebih dari 5 menit).'], 422);
        }

        // When a booking is cancelled by the student or tutor, remove it from the database
        // as requested. We still notify the counterparty before deletion.
        $booking->loadMissing(['payment', 'liveSession', 'review', 'tutorProfile.user', 'student']);

        $notifyUser = $request->user()->id === $booking->student_id
            ? $booking->tutorProfile->user
            : $booking->student;

        // Send notification about cancellation
        $notifyUser->notify(new BookingStatusNotification($booking, 'cancelled'));

        DB::transaction(function () use ($booking, $validated): void {
            // Optionally record cancel reason before deletion (if audit required, not persisted here)

            // Delete related records first to maintain referential integrity
            if ($booking->payment) {
                $booking->payment->delete();
            }

            if ($booking->liveSession) {
                $booking->liveSession->delete();
            }

            if ($booking->review) {
                $booking->review->delete();
            }

            // Finally delete the booking itself
            $booking->delete();
        });

        return response()->json(['message' => 'Booking dibatalkan dan dihapus dari database.']);
    }

    /** Menandai sesi selesai (dipanggil otomatis saat Live Class berakhir, atau manual oleh tutor). */
    public function complete(Request $request, Booking $booking)
    {
        $user = $request->user();

        if (! $user->isTutor() || $booking->tutorProfile->user_id !== $user->id) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        if ($booking->status === 'completed') {
            return new BookingResource($booking);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'completed']);

            $tutorProfile = $booking->tutorProfile;
            $tutorProfile->increment('total_sessions');
            $tutorProfile->increment('balance', $booking->price);

            $alreadyStudent = Booking::where('tutor_profile_id', $tutorProfile->id)
                ->where('student_id', $booking->student_id)
                ->where('status', 'completed')
                ->where('id', '!=', $booking->id)
                ->exists();

            if (! $alreadyStudent) {
                $tutorProfile->increment('total_students');
            }

            $booking->student->studyLogs()->create([
                'subject_id' => $booking->subject_id,
                'booking_id' => $booking->id,
                'date' => $booking->date,
                'duration_minutes' => $booking->duration_minutes,
            ]);
        });

        $booking->student->notify(new BookingStatusNotification($booking, 'completed'));

        return new BookingResource($booking->fresh(['tutorProfile.user', 'student', 'subject', 'payment']));
    }

    /**
     * Soft delete booking (hide dari tampilan, tapi data tetap di database).
     */
    public function destroy(Request $request, Booking $booking)
    {
        $this->authorizeAccess($request, $booking);

        // Hanya siswa yang bisa hide booking
        if ($request->user()->id !== $booking->student_id) {
            return response()->json(['message' => 'Hanya siswa yang dapat menghapus booking.'], 403);
        }

        $booking->update(['is_hidden' => true]);

        return response()->json(['message' => 'Booking dihapus dari tampilan.']);
    }

    /**
     * Bulk soft delete bookings.
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
        ]);

        $user = $request->user();

        if (! $user->isSiswa()) {
            return response()->json(['message' => 'Hanya siswa yang dapat menghapus booking.'], 403);
        }

        // Pastikan semua booking adalah milik user yang login
        $bookings = Booking::whereIn('id', $validated['booking_ids'])
            ->where('student_id', $user->id)
            ->get();

        if ($bookings->count() !== count($validated['booking_ids'])) {
            return response()->json(['message' => 'Beberapa booking tidak ditemukan atau bukan milik Anda.'], 422);
        }

        Booking::whereIn('id', $validated['booking_ids'])
            ->where('student_id', $user->id)
            ->update(['is_hidden' => true]);

        return response()->json(['message' => 'Booking berhasil dihapus dari tampilan.', 'deleted_count' => $bookings->count()]);
    }

    protected function authorizeAccess(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->id === $booking->student_id || $user->id === $booking->tutorProfile->user_id,
            403,
            'Tidak diizinkan mengakses booking ini.'
        );
    }
}