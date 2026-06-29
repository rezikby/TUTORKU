<?php
/**
 * FILE: backend/app/Http/Controllers/Api/DashboardTutorController.php
 * STATUS: DIUBAH (tambah myStudents(), monthly_income)
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardTutorController extends Controller
{
    public function overview(Request $request)
    {
        $user = $request->user();
        // Update last_login_at setiap kali akses dashboard
        $user->update(['last_login_at' => now()]);
        
        $tutorProfile = $user->tutorProfile()->firstOrFail();

        $upcoming = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->where('status', 'confirmed')
            ->where('date', '>=', now()->toDateString())
            ->with(['student', 'subject'])
            ->orderBy('date')->orderBy('start_time')
            ->limit(5)
            ->get();

        // Hitung total siswa aktif (unique siswa yang punya booking confirmed/completed)
        $totalActiveStudents = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->distinct('student_id')
            ->count('student_id');

        // Hitung total sesi = jumlah bookings yang confirmed + completed
        $totalSessions = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        // Hitung total pendapatan (confirmed + completed)
        $totalIncome = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('price');

        // Hitung saldo: pendapatan - potongan 10% admin
        $adminFee = $totalIncome * 0.10;
        $balance = $totalIncome - $adminFee;

        // Pendapatan 6 bulan terakhir (confirmed + completed = sudah dibayar)
        $monthlyIncome = collect(range(5, 0))->map(function ($monthsAgo) use ($tutorProfile) {
            $month = Carbon::now()->subMonths($monthsAgo);

            $income = Booking::where('tutor_profile_id', $tutorProfile->id)
                ->whereIn('status', ['confirmed', 'completed'])
                ->whereYear('date', $month->year)
                ->whereMonth('date', $month->month)
                ->sum('price');

            return [
                'month' => $month->translatedFormat('M'),
                'income' => (int) $income,
            ];
        });

        // Daftar booking yang berhasil (confirmed + completed) untuk halaman pendapatan
        $successfulBookings = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->with(['student', 'subject'])
            ->latest('date')
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'code' => $booking->code,
                    'date' => $booking->date->format('Y-m-d'),
                    'start_time' => $booking->start_time,
                    'duration_minutes' => $booking->duration_minutes,
                    'price' => (int) $booking->price,
                    'status' => $booking->status,
                    'student' => [
                        'id' => $booking->student->id,
                        'name' => $booking->student->name,
                    ],
                    'subject' => [
                        'name' => $booking->subject?->name ?? 'Umum',
                    ],
                ];
            })
            ->values()
            ->take(50);

        return response()->json([
            'verification_status' => $tutorProfile->verification_status,
            'registration_step' => $tutorProfile->registration_step,
            'balance' => (int) $balance,
            'admin_fee' => (int) $adminFee,
            'total_income' => (int) $totalIncome,
            'rating_avg' => (float) $tutorProfile->rating_avg,
            'rating_count' => $tutorProfile->rating_count,
            'total_students' => $totalActiveStudents,
            'total_sessions' => $totalSessions,
            'upcoming_sessions' => BookingResource::collection($upcoming),
            'monthly_income' => $monthlyIncome,
            'successful_bookings' => $successfulBookings->toArray(),
        ]);
    }

    /**
     * MURID SAYA — daftar siswa unik yang pernah/sedang booking dengan tutor ini,
     * beserta ringkasan jumlah sesi dan sesi terakhir.
     */
    public function myStudents(Request $request)
    {
        $tutorProfile = $request->user()->tutorProfile()->firstOrFail();

        $students = Booking::where('tutor_profile_id', $tutorProfile->id)
            ->whereIn('status', ['confirmed', 'completed'])
            ->with('student')
            ->get()
            ->groupBy('student_id')
            ->map(function ($bookings) {
                $student = $bookings->first()->student;
                $completed = $bookings->where('status', 'completed');

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'avatar' => $student->avatar_url,
                    'total_sessions' => $completed->count(),
                    'last_session_date' => $bookings->max('date'),
                ];
            })
            ->values();

        return response()->json(['data' => $students]);
    }
}
