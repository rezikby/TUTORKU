<?php
/**
 * FILE: backend/app/Http/Controllers/Api/ReviewController.php
 * STATUS: DIUBAH (tambah myReviews())
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function store(Request $request, Booking $booking)
    {
        $user = $request->user();
        abort_unless($user->id === $booking->student_id, 403, 'Hanya siswa pemilik booking yang dapat memberi ulasan.');
        abort_unless($booking->status === 'completed', 422, 'Ulasan hanya bisa diberikan setelah sesi selesai.');

        if ($booking->review()->exists()) {
            return response()->json(['message' => 'Booking ini sudah diberi ulasan.'], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = DB::transaction(function () use ($booking, $validated, $user) {
            $review = $booking->review()->create([
                'student_id' => $user->id,
                'tutor_profile_id' => $booking->tutor_profile_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);

            $tutorProfile = $booking->tutorProfile;
            $newCount = $tutorProfile->rating_count + 1;
            $newAvg = (($tutorProfile->rating_avg * $tutorProfile->rating_count) + $validated['rating']) / $newCount;

            $tutorProfile->update([
                'rating_count' => $newCount,
                'rating_avg' => round($newAvg, 2),
            ]);

            return $review;
        });

        return new ReviewResource($review->load('student'));
    }

    public function index(Request $request, int $tutorProfileId)
    {
        $reviews = \App\Models\Review::where('tutor_profile_id', $tutorProfileId)
            ->with('student')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ReviewResource::collection($reviews);
    }

    /** GET /api/tutor/reviews — tutor melihat review yang diterima dari siswa. */
    public function myReviews(Request $request)
    {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();

        $reviews = \App\Models\Review::where('tutor_profile_id', $tutorProfile->id)
            ->with('student')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ReviewResource::collection($reviews);
    }
}
