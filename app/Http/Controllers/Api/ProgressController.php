<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AchievementResource;
use App\Http\Resources\StudyLogResource;
use App\Models\Achievement;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user();

        $totalCompleted = Booking::where('student_id', $student->id)->where('status', 'completed')->count();
        $totalBookings = Booking::where('student_id', $student->id)->whereIn('status', ['completed', 'cancelled'])->count();
        $attendanceRate = $totalBookings > 0 ? round(($totalCompleted / $totalBookings) * 100) : 0;

        $avgScore = $student->studyLogs()->whereNotNull('score')->avg('score');

        // Jam belajar per bulan, 6 bulan terakhir
        $monthly = collect(range(5, 0))->map(function ($monthsAgo) use ($student) {
            $month = Carbon::now()->subMonths($monthsAgo);

            $minutes = $student->studyLogs()
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('duration_minutes');

            return [
                'month' => $month->translatedFormat('M'),
                'hours' => round($minutes / 60, 1),
            ];
        });

        // Progress per mata pelajaran
        $bySubject = $student->studyLogs()
            ->selectRaw('subject_id, sum(duration_minutes) as total_minutes, count(*) as total_sessions')
            ->whereNotNull('subject_id')
            ->groupBy('subject_id')
            ->with('subject')
            ->get()
            ->map(fn ($row) => [
                'subject' => $row->subject?->name,
                'hours' => round($row->total_minutes / 60, 1),
                'sessions' => $row->total_sessions,
            ]);

        $earnedAchievements = $student->achievements()->with('achievement')->get();
        $allAchievements = Achievement::all();

        return response()->json([
            'attendance_rate' => $attendanceRate,
            'total_sessions_completed' => $totalCompleted,
            'average_score' => $avgScore ? round($avgScore, 1) : null,
            'monthly_study_hours' => $monthly,
            'progress_by_subject' => $bySubject,
            'recent_logs' => StudyLogResource::collection(
                $student->studyLogs()->with('subject')->latest('date')->limit(10)->get()
            ),
            'achievements_earned' => $earnedAchievements->map(fn ($ua) => [
                ...((new AchievementResource($ua->achievement))->resolve()),
                'earned_at' => $ua->earned_at,
            ]),
            'achievements_total' => $allAchievements->count(),
        ]);
    }
}
