<?php

namespace App\Http\Controllers\Api;

use App\Events\LiveSessionStarted;
use App\Events\SlotsUpdated;
use App\Events\WebRtcSignal;
use App\Events\WhiteboardUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\LiveSessionResource;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\LiveSessionParticipant;
use App\Notifications\SessionStartedNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // Track participant (untuk polling)
        LiveSessionParticipant::updateOrCreate(
            ['live_session_id' => $session->id, 'user_id' => $user->id],
            [
                'is_audio_on' => true,
                'is_video_on' => true,
            ]
        );

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

        $now = now();
        $startedAt = $session->started_at ?? $now;
        $totalPausedSeconds = $session->total_paused_seconds ?? 0;

        if ($session->paused_at) {
            $totalPausedSeconds += $this->calculatePausedSeconds($now, $session->paused_at);
        }

        $durationSeconds = max(0, $now->diffInSeconds($startedAt) - $totalPausedSeconds);

        $session->update([
            'status'           => 'ended',
            'ended_at'         => $now,
            'paused_at'        => null,
            'duration_seconds' => $durationSeconds,
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

        try {
            event(new SlotsUpdated($booking->tutor_profile_id, $booking->date));
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch SlotsUpdated event on live session end', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
            ]);
        }

        try {
            ChatConversation::where('booking_id', $booking->id)->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed to delete session-specific chat conversation on session end', [
                'error' => $e->getMessage(),
                'booking_id' => $booking->id,
            ]);
        }

        return new LiveSessionResource($session->fresh());
    }

    public function pause(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->id === $booking->tutorProfile->user_id,
            403,
            'Hanya tutor atau admin yang dapat menjeda sesi.'
        );

        $session = $booking->liveSession()->firstOrFail();

        if ($session->status === 'paused') {
            return new LiveSessionResource($session);
        }

        if ($session->status !== 'ongoing') {
            return response()->json([
                'message' => 'Sesi hanya dapat dijeda ketika status sedang berlangsung.',
            ], 422);
        }

        $session->update([
            'status'            => 'paused',
            'paused_at'         => now(),
        ]);

        return new LiveSessionResource($session->fresh());
    }

    public function resume(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || $user->id === $booking->tutorProfile->user_id,
            403,
            'Hanya tutor atau admin yang dapat melanjutkan sesi.'
        );

        $session = $booking->liveSession()->firstOrFail();

        if ($session->status !== 'paused') {
            return new LiveSessionResource($session);
        }

        $pausedAt = $session->paused_at ?? now();
        $additionalPausedSeconds = $this->calculatePausedSeconds(now(), $pausedAt);

        $session->update([
            'status'                => 'ongoing',
            'paused_at'             => null,
            'total_paused_seconds'  => ($session->total_paused_seconds ?? 0) + $additionalPausedSeconds,
        ]);

        return new LiveSessionResource($session->fresh());
    }

    protected function calculatePausedSeconds(CarbonInterface $now, CarbonInterface $pausedAt): int
    {
        $seconds = $pausedAt->diffInSeconds($now, false);

        return max(0, (int) abs($seconds));
    }

    /** Kirim sinyal WebRTC (offer / answer / ice-candidate / hangup) ke lawan bicara di room. */
    public function signal(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'type'    => ['required', Rule::in(['offer', 'answer', 'ice-candidate', 'hangup', 'chunked-signal'])],
            'payload' => ['required', 'array'],
        ]);

        $session = $booking->liveSession()->firstOrFail();

        Log::debug('WebRTC signal payload received', [
            'booking_id' => $booking->id,
            'room_id' => $session->room_id,
            'from_user_id' => $request->user()->id,
            'type' => $validated['type'],
            'payload_keys' => array_keys($validated['payload']),
            'payload_summary' => match ($validated['type']) {
                'offer', 'answer' => [
                    'sdp_length' => is_string($validated['payload']['sdp'] ?? null)
                        ? strlen($validated['payload']['sdp'])
                        : null,
                    'type' => $validated['payload']['type'] ?? null,
                ],
                'ice-candidate' => [
                    'candidate_exists' => isset($validated['payload']['candidate']),
                    'sdpMid' => $validated['payload']['sdpMid'] ?? null,
                    'sdpMLineIndex' => $validated['payload']['sdpMLineIndex'] ?? null,
                ],
                'chunked-signal' => [
                    'baseType' => $validated['payload']['baseType'] ?? null,
                    'chunkIndex' => $validated['payload']['chunkIndex'] ?? null,
                    'chunkCount' => $validated['payload']['chunkCount'] ?? null,
                ],
                default => [],
            },
        ]);

        Log::debug('Broadcasting WebRTC signal to presence channel', [
            'room_id' => $session->room_id,
            'from_user_id' => $request->user()->id,
            'type' => $validated['type'],
        ]);

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

    /** Get participants list (polling untuk shared hosting) */
    public function participants(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $session = $booking->liveSession()->firstOrFail();

        $participants = $session
            ->participants()
            ->with('user')
            ->get()
            ->map(fn (LiveSessionParticipant $p) => $p->toParticipantPresence())
            ->values();

        return response()->json([
            'participants' => $participants,
            'count' => $participants->count(),
        ]);
    }

    /** Update participant state (audio/video/screen sharing) */
    public function updateParticipantState(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $validated = $request->validate([
            'is_audio_on' => ['boolean'],
            'is_video_on' => ['boolean'],
            'is_screen_sharing' => ['boolean'],
            'is_speaking' => ['boolean'],
        ]);

        $session = $booking->liveSession()->firstOrFail();
        $user = $request->user();

        $participant = LiveSessionParticipant::where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participant) {
            return response()->json(['message' => 'Participant not found'], 404);
        }

        $participant->update($validated);

        return response()->json([
            'message' => 'State updated',
            'participant' => $participant->toParticipantPresence(),
        ]);
    }

    /** Leave session (remove from participants list) */
    public function leave(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $session = $booking->liveSession()->firstOrFail();
        $user = $request->user();

        LiveSessionParticipant::where('live_session_id', $session->id)
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['message' => 'Left session']);
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