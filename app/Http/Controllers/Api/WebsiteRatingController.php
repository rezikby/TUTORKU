<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as BaseController;
use App\Models\WebsiteRating;
use Illuminate\Http\Request;


class WebsiteRatingController extends BaseController
{
    /**
     * Display a listing of the resource (for testimonials - public).
     */
    public function index()
    {
        $ratings = WebsiteRating::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'name' => $r->user?->name ?? 'Anonymous',
                'role' => $r->user?->role ?? 'Siswa',
                'photo' => $r->user?->avatar ? asset("storage/{$r->user->avatar}") : null,
                'text' => $r->review ?? '',
                'rating' => $r->rating,
                'created_at' => $r->created_at,
            ]);

        $average = WebsiteRating::avg('rating');

        return response()->json([
            'data' => $ratings,
            'total' => WebsiteRating::count(),
            'average' => $average ? round($average, 1) : 0,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
            'booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $rating = WebsiteRating::create([
            'user_id' => auth()->id(),
            'rating' => $request->input('rating'),
            'review' => $request->input('review'),
            'booking_id' => $request->input('booking_id'),
        ]);

        return response()->json([
            'message' => 'Rating berhasil disimpan',
            'data' => $rating,
        ], 201);
    }
}

