<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Report;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Support\Carbon;

class AdminDashboardController extends Controller
{
    public function overview()
    {
        $monthlyRevenue = collect(range(5, 0))->map(function ($monthsAgo) {
            $month = Carbon::now()->subMonths($monthsAgo);

            $revenue = Payment::where('status', 'paid')
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('amount');

            return ['month' => $month->translatedFormat('M'), 'revenue' => (int) $revenue];
        });

        return response()->json([
            'total_users' => User::count(),
            'total_siswa' => User::where('role', 'siswa')->count(),
            'total_tutor' => User::where('role', 'tutor')->count(),
            'tutor_verified' => TutorProfile::where('verification_status', 'verified')->count(),
            'tutor_pending' => TutorProfile::where('verification_status', 'pending')->where('registration_submitted', true)->count(),
            'total_bookings' => Booking::count(),
            'total_bookings_completed' => Booking::where('status', 'completed')->count(),
            'total_revenue' => (int) Payment::where('status', 'paid')->sum('amount'),
            'monthly_revenue' => $monthlyRevenue,
            'open_reports' => Report::where('status', 'open')->count(),
            'new_contact_messages' => ContactMessage::where('status', 'new')->count(),
        ]);
    }
}
