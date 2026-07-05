<?php
/**
 * FILE: backend/app/Http/Controllers/Api/TutorController.php
 * STATUS: DIUBAH (tambah available-slots, like/dislike, favorit)
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\TutorProfileResource;
use App\Models\Booking;
use App\Models\TutorAvailability;
use App\Models\TutorFavorite;
use App\Models\TutorLike;
use App\Models\TutorProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TutorController extends Controller
{
    /**
     * GET /api/tutors — Halaman "Cari Tutor" dengan filter advanced.
     */
    public function index(Request $request)
    {
        $query = TutorProfile::query()
            ->verified()
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->with(['user', 'subjects']);

        // Pencarian bebas: nama tutor atau mata pelajaran
        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('subjects', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter mata pelajaran
        if ($subjectId = $request->input('subject_id')) {
            $query->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectId));
        }

        // Filter jenjang (SD/SMP/SMA/Mahasiswa)
        if ($level = $request->input('level')) {
            $query->whereJsonContains('levels', $level);
        }

        // Filter harga
        if ($request->filled('price_max')) {
            $query->where('price_per_hour', '<=', (int) $request->input('price_max'));
        }
        if ($request->filled('price_min')) {
            $query->where('price_per_hour', '>=', (int) $request->input('price_min'));
        }

        // Filter rating minimum
        if ($request->filled('min_rating')) {
            $query->where('rating_avg', '>=', (float) $request->input('min_rating'));
        }

        // Filter lokasi
        if ($city = $request->input('city')) {
            $query->where('city', 'like', "%{$city}%");
        }

        // Filter online/offline
        if ($mode = $request->input('mode')) {
            if ($mode === 'online') {
                $query->where('mode_online', true);
            } elseif ($mode === 'offline') {
                $query->where('mode_offline', true);
            }
        }

        // Sorting
        $sort = $request->input('sort', 'rating');
        match ($sort) {
            'price_asc' => $query->orderBy('price_per_hour', 'asc'),
            'price_desc' => $query->orderBy('price_per_hour', 'desc'),
            'experience' => $query->orderBy('experience_years', 'desc'),
            default => $query->orderByDesc('rating_avg'),
        };

        $tutors = $query->paginate($request->integer('per_page', 12));

        return TutorProfileResource::collection($tutors);
    }

    /**
     * GET /api/tutors/{tutorProfile} — Halaman Detail Tutor.
     */
    public function show(Request $request, TutorProfile $tutorProfile)
    {
        $tutorProfile->increment('view_count');

        $tutorProfile->load([
            'user', 'subjects', 'educations', 'experiences', 'certificates', 'availabilities.subject',
        ]);

        $tutorProfile->loadCount(['bookings' => fn ($query) => $query->whereNotIn('status', ['cancelled', 'rejected'])]);

        $myVote = null;
        $isFavorited = false;
        $myBookingsCount = 0;

        if ($request->user()) {
            $myVote = TutorLike::where('tutor_profile_id', $tutorProfile->id)
                ->where('user_id', $request->user()->id)
                ->value('type');

            $isFavorited = TutorFavorite::where('tutor_profile_id', $tutorProfile->id)
                ->where('user_id', $request->user()->id)
                ->exists();

            if ($request->user()->isSiswa()) {
                $myBookingsCount = Booking::where('tutor_profile_id', $tutorProfile->id)
                    ->where('student_id', $request->user()->id)
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->count();
            }
        }

        $tutorProfile->my_bookings_count = $myBookingsCount;

        return (new TutorProfileResource($tutorProfile))->additional([
            'reviews' => ReviewResource::collection(
                $tutorProfile->reviews()->with('student')->latest()->limit(20)->get()
            ),
            'my_vote' => $myVote,
            'is_favorited' => $isFavorited,
        ]);
    }

    /**
     * GET /api/tutors/{tutorProfile}/available-slots?date=YYYY-MM-DD
     * Dipakai di halaman Booking: tanggal hanya menampilkan jadwal yang tersedia
     * (berdasarkan TutorAvailability per hari), dan slot yang sudah dibooking disembunyikan.
     *
     * Tanpa parameter 'date': mengembalikan daftar 30 hari ke depan yang punya
     * minimal 1 slot tersedia (dipakai untuk menandai tanggal aktif di date-picker).
     */
    public function availableSlots(Request $request, TutorProfile $tutorProfile)
    {
        $availabilities = $tutorProfile->availabilities()->where('is_active', true)->get();

        if ($request->filled('date')) {
            $request->validate(['date' => ['date_format:Y-m-d']]);

            $date = Carbon::parse($request->input('date'));

            return response()->json([
                'date' => $date->toDateString(),
                'slots' => $this->slotsForDate($tutorProfile, $availabilities, $date),
            ]);
        }

        // Tanpa tanggal spesifik: kembalikan ringkasan 30 hari ke depan.
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $date = Carbon::today()->addDays($i);
            $slots = $this->slotsForDate($tutorProfile, $availabilities, $date);

            $days[] = [
                'date' => $date->toDateString(),
                'day_of_week' => $date->dayOfWeek,
                'has_available_slot' => count(array_filter($slots, fn ($s) => $s['available'])) > 0,
                'has_schedule' => $this->hasScheduleForDate($availabilities, $date),
            ];
        }

        return response()->json(['days' => $days]);
    }

    protected function hasScheduleForDate($availabilities, Carbon $date): bool
    {
        if ($date->isBefore(Carbon::today())) {
            return false;
        }

        $dateAvailabilities = $availabilities->filter(function ($availability) use ($date) {
            return $availability->date && $availability->date->isSameDay($date);
        });

        if ($dateAvailabilities->isNotEmpty()) {
            return true;
        }

        return $availabilities->where('date', null)->where('day_of_week', $date->dayOfWeek)->isNotEmpty();
    }

    /** Hitung slot jam tersedia untuk satu tanggal: dari availability dikurangi booking yang sudah ada. */
    protected function slotsForDate(TutorProfile $tutorProfile, $availabilities, Carbon $date): array
    {
        // Tidak menampilkan tanggal yang sudah lewat.
        if ($date->isBefore(Carbon::today())) {
            return [];
        }

        $dateAvailabilities = $availabilities->filter(function ($availability) use ($date) {
            return $availability->date && $availability->date->isSameDay($date);
        });

        if ($dateAvailabilities->isNotEmpty()) {
            $dayAvailabilities = $dateAvailabilities;
        } else {
            $dayAvailabilities = $availabilities->where('date', null)->where('day_of_week', $date->dayOfWeek);
        }

        if ($dayAvailabilities->isEmpty()) {
            return [];
        }

        // Slot dianggap terpakai hanya untuk booking aktif (pending/confirmed).
        // Setelah sesi selesai dan status menjadi completed, waktu tersebut harus
        // tersedia kembali untuk ditampilkan ulang di kalender jika tanggal belum lewat.
        $bookedTimes = [];

        Booking::where('tutor_profile_id', $tutorProfile->id)
            ->where('date', $date->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->get()
            ->each(function (Booking $booking) use (&$bookedTimes) {
                $startTime = Carbon::parse($booking->start_time);
                $endTime = $startTime->copy()->addMinutes($booking->duration_minutes);

                $cursor = $startTime->copy();
                while ($cursor->lt($endTime)) {
                    $bookedTimes[] = $cursor->format('H:i');
                    $cursor->addMinutes(10);
                }
            });

        $bookedTimes = array_values(array_unique($bookedTimes));

        $slots = [];

        foreach ($dayAvailabilities as $availability) {
            $cursor = Carbon::parse($availability->start_time);
            $end = Carbon::parse($availability->end_time);

            while ($cursor->lt($end)) {
                $label = $cursor->format('H:i');
                $isPastToday = $date->isToday() && $cursor->lt(Carbon::now());

                $slots[] = [
                    'time' => $label,
                    'available' => ! in_array($label, $bookedTimes, true) && ! $isPastToday,
                ];

                $cursor->addMinutes(10);
            }
        }

        return $slots;
    }

    /** POST /api/tutors/{tutorProfile}/like */
    public function like(Request $request, TutorProfile $tutorProfile)
    {
        return $this->vote($request, $tutorProfile, 'like');
    }

    /** POST /api/tutors/{tutorProfile}/dislike */
    public function dislike(Request $request, TutorProfile $tutorProfile)
    {
        return $this->vote($request, $tutorProfile, 'dislike');
    }

    protected function vote(Request $request, TutorProfile $tutorProfile, string $type)
    {
        $userId = $request->user()->id;
        $existing = TutorLike::where('tutor_profile_id', $tutorProfile->id)->where('user_id', $userId)->first();

        if ($existing && $existing->type === $type) {
            // Klik kedua kali pada vote yang sama -> batalkan vote.
            $existing->delete();
            $myVote = null;
        } elseif ($existing) {
            $existing->update(['type' => $type]);
            $myVote = $type;
        } else {
            TutorLike::create(['tutor_profile_id' => $tutorProfile->id, 'user_id' => $userId, 'type' => $type]);
            $myVote = $type;
        }

        $tutorProfile->update([
            'like_count' => TutorLike::where('tutor_profile_id', $tutorProfile->id)->where('type', 'like')->count(),
            'dislike_count' => TutorLike::where('tutor_profile_id', $tutorProfile->id)->where('type', 'dislike')->count(),
        ]);

        return response()->json([
            'like_count' => $tutorProfile->like_count,
            'dislike_count' => $tutorProfile->dislike_count,
            'my_vote' => $myVote,
        ]);
    }

    /** GET /api/favorites — daftar tutor favorit siswa (Dashboard Siswa > Favorit). */
    public function favorites(Request $request)
    {
        $favorites = $request->user()->favoriteTutors()
            ->with(['tutorProfile.user', 'tutorProfile.subjects'])
            ->latest()
            ->get()
            ->pluck('tutorProfile');

        return TutorProfileResource::collection($favorites);
    }

    /** POST /api/tutors/{tutorProfile}/favorite — toggle tambah/hapus favorit. */
    public function toggleFavorite(Request $request, TutorProfile $tutorProfile)
    {
        $userId = $request->user()->id;

        $favorite = TutorFavorite::where('user_id', $userId)
            ->where('tutor_profile_id', $tutorProfile->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json(['favorited' => false, 'message' => 'Dihapus dari favorit.']);
        }

        TutorFavorite::create(['user_id' => $userId, 'tutor_profile_id' => $tutorProfile->id]);

        return response()->json(['favorited' => true, 'message' => 'Ditambahkan ke favorit.']);
    }
}