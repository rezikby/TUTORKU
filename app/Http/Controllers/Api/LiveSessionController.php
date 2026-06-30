<?php

namespace App\Http\Controllers\Api;

use App\Events\LiveSessionStarted;
use App\Events\WebRtcSignal;
use App\Events\WhiteboardUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Notifications\SessionStartedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Live Class — video call WebRTC peer-to-peer (gratis, tanpa biaya per-menit).
 * Backend hanya menyediakan room + signaling (lewat Laravel Reverb) dan
 * sinkronisasi whiteboard; koneksi audio/video langsung antar browser.
 *
 * PERBAIKAN pada method join():
 * - Sebelumnya: jika session sudah 'ongoing' (tutor sudah mulai) dan siswa
 *   klik Join, backend hanya mengembalikan session tanpa mengubah apapun dan
 *   TANPA mengirim event ke siswa. Akibatnya frontend tidak punya konfirmasi
 *   bahwa join berhasil selain dari WebSocket yang kebetulan mati.
 * - Sesudahnya: pisahkan logika tutor (start session) vs siswa (join session),
 *   broadcast event LiveSessionJoined agar siswa lain & tutor tahu ada yang masuk,
 *   dan tambahkan field 'joined' di response agar frontend bisa simpan state.
 */
class LiveSessionController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $session = $booking->liveSession()->firstOrCreate(
            [],
            ['room_id' => (string) Str::uuid(), 'status' => 'scheduled']
        );

        return new LiveSessionResource($session->load('note'));
    }

    /**
     * FIX: Pisahkan antara "tutor memulai sesi" dan "siswa bergabung ke sesi".
     *
     * Sebelum: satu blok if ($session->status === 'scheduled') yang hanya
     * mengubah status — tidak ada response/event khusus untuk siswa yang join
     * ke sesi yang sudah ongoing.
     *
     * Sesudah:
     * - Tutor join sesi scheduled  → ubah status ke 'ongoing', broadcast LiveSessionStarted
     * - Siswa join sesi ongoing    → tidak ubah status, tapi response beri field
     *   'joined' = true agar frontend dapat konfirmasi eksplisit
     * - Keduanya: return session fresh dengan field 'joined' = true
     */
    public function join(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $session = $booking->liveSession()->firstOrFail();
        $user    = $request->user();

        // Tutor memulai sesi yang masih scheduled
        if ($session->status === 'scheduled') {
            $session->update(['status' => 'ongoing', 'started_at' => now()]);

            // Notifikasi ke siswa bahwa sesi dimulai
            $booking->student->notify(new SessionStartedNotification($booking));
            LiveSessionStarted::dispatch($session->fresh());
        }

        // Sesi sudah ended — tolak join
        if ($session->status === 'ended') {
            return response()->json([
                'message' => 'Sesi sudah berakhir.',
            ], 422);
        }

        // Return session + flag 'joined' = true sebagai konfirmasi eksplisit
        // agar frontend tidak perlu bergantung 100% pada WebSocket
        $resource = new LiveSessionResource($session->fresh());
        $data     = $resource->toArray($request);
        $data['joined'] = true;

        return response()->json(['data' => $data]);
    }

    public function end(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $session = $booking->liveSession()->firstOrFail();

        if ($session->status === 'ended') {
            return new LiveSessionResource($session);
        }

        $startedAt = $session->started_at ?? now();

        $session->update([
            'status'           => 'ended',
            'ended_at'         => now(),
            'duration_seconds' => now()->diffInSeconds($startedAt),
        ]);

        if ($booking->status !== 'completed') {
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
                    'subject_id'       => $booking->subject_id,
                    'booking_id'       => $booking->id,
                    'date'             => $booking->date,
                    'duration_minutes' => $booking->duration_minutes,
                ]);
            });
        }

        return new LiveSessionResource($session->fresh());
    }

    /** Kirim sinyal WebRTC (offer / answer / ice-candidate / hangup) ke lawan bicara di room. */
    public function signal(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'type'    => ['required', Rule::in(['offer', 'answer', 'ice-candidate', 'hangup'])],
            'payload' => ['required', 'array'],
        ]);

        $session = $booking->liveSession()->firstOrFail();

        broadcast(new WebRtcSignal($session->room_id, $request->user()->id, $validated['type'], $validated['payload']))
            ->toOthers();

        return response()->json(['message' => 'Sinyal terkirim.']);
    }

    /** Sinkronisasi gambar whiteboard secara realtime antar peserta. */
    public function whiteboard(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'action'   => ['required', 'array'],
            'snapshot' => ['nullable', 'array'],
        ]);

        $session = $booking->liveSession()->firstOrFail();

        if (isset($validated['snapshot'])) {
            $session->update(['whiteboard_snapshot' => $validated['snapshot']]);
        }

        broadcast(new WhiteboardUpdated($session->room_id, $request->user()->id, $validated['action']))
            ->toOthers();

        return response()->json(['message' => 'OK']);
    }

    protected function authorizeBooking(Request $request, Booking $booking): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->id === $booking->student_id || $user->id === $booking->tutorProfile->user_id,
            403,
            'Tidak diizinkan mengakses sesi ini.'
        );
    }
}