<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorProfileResource;
use App\Models\TutorProfile;
use App\Notifications\TutorAccountCreatedNotification;
use App\Notifications\TutorVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TutorVerificationController extends Controller
{
    public function index(Request $request)
    {
        $query = TutorProfile::query()->with(['user', 'subjects', 'educations', 'certificates']);

        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('verification_status', $status);
            if ($status === 'pending') {
                $query->where('registration_submitted', true);
            }
        }

        return TutorProfileResource::collection($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function show(TutorProfile $tutorProfile)
    {
        return new TutorProfileResource(
            $tutorProfile->load(['user', 'subjects', 'educations', 'experiences', 'certificates', 'availabilities'])
        );
    }

    public function approve(TutorProfile $tutorProfile)
    {
        abort_unless($tutorProfile->registration_submitted, 422, 'Pengajuan ini belum disubmit oleh tutor.');

        $tutorProfile->update([
            'verification_status' => 'verified',
            'verification_note' => null,
            'badge' => $tutorProfile->badge ?? 'Verified',
        ]);

        // Sesuai instruksi: jika admin menyetujui, user otomatis menjadi tutor
        // dan role otomatis berubah (dashboard tutor otomatis aktif lewat middleware role:tutor).
        // Generate password baru karena tutor login lewat halaman terpisah (email+password,
        // bisa juga Google) tanpa OTP -- berbeda dari siswa yang tetap pakai OTP.
        $plainPassword = Str::random(10);

        $tutorProfile->user->update([
            'role' => 'tutor',
            'password' => Hash::make($plainPassword),
        ]);

        $tutorProfile->user->notify(new TutorVerificationNotification($tutorProfile));
        $tutorProfile->user->notify(new TutorAccountCreatedNotification($plainPassword));

        return new TutorProfileResource($tutorProfile->fresh(['user']));
    }

    public function reject(Request $request, TutorProfile $tutorProfile)
    {
        abort_unless($tutorProfile->registration_submitted, 422, 'Pengajuan ini belum disubmit oleh tutor.');

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $tutorProfile->update([
            'verification_status' => 'rejected',
            'verification_note' => $validated['note'],
        ]);

        $tutorProfile->user->notify(new TutorVerificationNotification($tutorProfile));

        return new TutorProfileResource($tutorProfile->fresh());
    }
}
