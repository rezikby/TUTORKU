<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardSiswaController extends Controller
{
    public function overview(Request $request)
    {
        $student = $request->user();

        $totalSessions = Booking::where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();

        $totalMinutes = Booking::where('student_id', $student->id)
            ->where('status', 'completed')
            ->sum('duration_minutes');

        $upcoming = Booking::where('student_id', $student->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where('date', '>=', now()->toDateString())
            ->with(['tutorProfile.user', 'subject'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->first();

        $favoriteTutor = Booking::where('student_id', $student->id)
            ->where('status', 'completed')
            ->selectRaw('tutor_profile_id, count(*) as total')
            ->groupBy('tutor_profile_id')
            ->orderByDesc('total')
            ->with('tutorProfile.user')
            ->first();

        $weekly = collect(range(6, 0))->map(function ($daysAgo) use ($student) {
            $date = Carbon::today()->subDays($daysAgo);

            $minutes = Booking::where('student_id', $student->id)
                ->where('status', 'completed')
                ->whereDate('date', $date)
                ->sum('duration_minutes');

            return [
                'date' => $date->toDateString(),
                'label' => $date->translatedFormat('D'),
                'minutes' => (int) $minutes,
            ];
        });

        $achievementsCount = 0;

        return response()->json([
            'total_sessions' => $totalSessions,
            'total_study_hours' => round($totalMinutes / 60, 1),
            'favorite_tutor' => $favoriteTutor?->tutorProfile?->user?->name ?? null,
            'upcoming_session' => $upcoming ? [
                'booking_id' => $upcoming->id,
                'tutor' => [
                    'name' => $upcoming->tutorProfile?->user?->name ?? 'Tutor',
                    'avatar' => $upcoming->tutorProfile?->user?->avatar_url ?? null,
                    'photo' => $upcoming->tutorProfile?->profile_photo_url ?? $upcoming->tutorProfile?->user?->avatar_url ?? null,
                ],
                'subject' => [
                    'name' => $upcoming->subject?->name ?? 'Mata Pelajaran',
                ],
                'date' => $upcoming->date,
                'start_time' => $upcoming->start_time,
            ] : null,
            'weekly_study_minutes' => $weekly,
            'achievements_count' => $achievementsCount,
        ]);
    }
}