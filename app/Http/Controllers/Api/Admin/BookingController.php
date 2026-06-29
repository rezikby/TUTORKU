<?php
/**
 * FILE: backend/app/Http/Controllers/Api/Admin/BookingController.php
 * STATUS: BARU
 */


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::query()->with(['student', 'tutorProfile.user', 'subject', 'payment']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->latest('date')->latest('start_time')->paginate($request->integer('per_page', 20));

        return BookingResource::collection($bookings);
    }

    public function show(Booking $booking)
    {
        return new BookingResource($booking->load(['student', 'tutorProfile.user', 'subject', 'payment', 'liveSession']));
    }

    /** Admin membatalkan booking secara paksa (misal terjadi sengketa). */
    public function cancel(Request $request, Booking $booking)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $booking->update(['status' => 'cancelled', 'cancel_reason' => $validated['reason']]);

        return new BookingResource($booking->fresh(['student', 'tutorProfile.user', 'subject', 'payment']));
    }
}
