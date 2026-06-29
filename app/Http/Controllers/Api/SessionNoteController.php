<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionNoteResource;
use App\Models\Booking;
use Illuminate\Http\Request;

/** Rekap & Catatan Sesi — dibuat tutor setelah Live Class berakhir. */
class SessionNoteController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($user->isTutor() && $booking->tutorProfile->user_id === $user->id, 403);

        $session = $booking->liveSession()->firstOrFail();

        $validated = $request->validate([
            'generated_summary' => ['nullable', 'string', 'max:5000'],
            'progress_notes' => ['nullable', 'string', 'max:5000'],
            'tasks' => ['nullable', 'array'],
            'tasks.*' => ['string', 'max:255'],
        ]);

        $note = $session->note()->updateOrCreate([], [
            ...$validated,
            'created_by' => $user->id,
        ]);

        return new SessionNoteResource($note);
    }

    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $user->id === $booking->student_id || $user->id === $booking->tutorProfile->user_id,
            403
        );

        $note = $booking->liveSession?->note;

        return new SessionNoteResource($note);
    }
}
