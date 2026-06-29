<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\TutorProfile;
use App\Models\User;

class PlatformController extends Controller
{
    /** Statistik ringkas untuk Landing Page. */
    public function stats()
    {
        return response()->json([
            'total_tutors' => TutorProfile::verified()->count(),
            'total_students' => User::where('role', 'siswa')->count(),
            'total_cities' => TutorProfile::verified()->whereNotNull('city')->distinct('city')->count('city'),
            'satisfaction_rate' => round((float) Review::avg('rating') * 20, 1), // konversi skala 5 -> persen
            'total_sessions' => \App\Models\Booking::where('status', 'completed')->count(),
        ]);
    }
}
