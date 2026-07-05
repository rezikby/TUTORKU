<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LiveSession;
use App\Models\StudyLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardSiswaController extends Controller
{
    public function overview(Request $request)
    {
        $student = $request->user();

        $studyLogCount = StudyLog::where('student_id', $student->id)->count();
        $studyLogMinutes = StudyLog::where('student_id', $student->id)->sum('duration_minutes');

        $sessionQuery = LiveSession::whereHas('booking', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->where('status', 'ended');

        $endedSessionCount = $sessionQuery->count();
        $endedSessionSeconds = $sessionQuery->sum('duration_seconds');

        $totalSessions = $studyLogCount > 0 ? $studyLogCount : $endedSessionCount;
        $totalMinutes = $studyLogCount > 0 ? $studyLogMinutes : (int) round($endedSessionSeconds / 60);

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

        $month = $request->query('month', 'this');
        $today = Carbon::today();

        if ($month === 'last') {
            $start = $today->copy()->subMonthNoOverflow()->firstOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $start = $today->copy()->firstOfMonth();
            $end = $today->copy()->endOfMonth();
        }

        $dailyStudyLogs = StudyLog::where('student_id', $student->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('date, SUM(duration_minutes) as minutes, COUNT(*) as completed_sessions')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(function ($row) {
                $dateString = $row->date instanceof \Illuminate\Support\Carbon ? $row->date->toDateString() : (string) $row->date;
                return [$dateString => $row];
            });

        $sessionsByDate = LiveSession::whereHas('booking', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->where('status', 'ended')
            ->whereBetween('ended_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw('DATE(ended_at) as date, SUM(duration_seconds) as seconds, COUNT(*) as completed_sessions')
            ->groupBy(DB::raw('DATE(ended_at)'))
            ->get()
            ->keyBy('date');

        $monthly = collect();
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();
            $summary = $dailyStudyLogs->get($dateKey);
            $minutes = $summary ? (int) $summary->minutes : 0;
            $completedSessions = $summary ? (int) $summary->completed_sessions : 0;

            if (!$summary && $sessionsByDate->has($dateKey)) {
                $sessionSummary = $sessionsByDate->get($dateKey);
                $minutes = (int) round($sessionSummary->seconds / 60);
                $completedSessions = (int) $sessionSummary->completed_sessions;
            }

            $monthly->push([
                'date' => $dateKey,
                'label' => $date->format('d'),
                'minutes' => $minutes,
                'completed_sessions' => $completedSessions,
            ]);
        }

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
            'monthly_study_minutes' => $monthly,
            'achievements_count' => $achievementsCount,
        ]);
    }
}