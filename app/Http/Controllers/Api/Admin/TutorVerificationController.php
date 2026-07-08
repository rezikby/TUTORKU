<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorProfileResource;
use App\Models\TutorProfile;
use App\Notifications\TutorAccountCreatedNotification;
use App\Notifications\TutorVerificationNotification;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Facades\Notification;
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

        Notification::sendNow($tutorProfile->user, new TutorVerificationNotification($tutorProfile), ['database', 'mail', WhatsAppChannel::class]);
        Notification::sendNow($tutorProfile->user, new TutorAccountCreatedNotification($plainPassword), ['mail']);

        return new TutorProfileResource($tutorProfile->fresh(['user']));
    }

    public function update(Request $request, TutorProfile $tutorProfile)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$tutorProfile->user_id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'price_per_hour' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'experience_years' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'province' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'levels' => ['sometimes', 'nullable', 'array'],
            'levels.*' => ['string', 'max:100'],
            'mode_online' => ['sometimes', 'boolean'],
            'mode_offline' => ['sometimes', 'boolean'],
            'verification_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $userUpdates = [];
        if (array_key_exists('name', $validated)) {
            $userUpdates['name'] = $validated['name'];
        }
        if (array_key_exists('email', $validated)) {
            $userUpdates['email'] = $validated['email'];
        }
        if (array_key_exists('phone', $validated)) {
            $userUpdates['phone'] = $validated['phone'];
        }
        if (! empty($validated['password'])) {
            $userUpdates['password'] = Hash::make($validated['password']);
        }

        if (! empty($userUpdates)) {
            $tutorProfile->user->update($userUpdates);
        }

        $profileUpdates = collect($validated)
            ->only([
                'headline',
                'bio',
                'price_per_hour',
                'experience_years',
                'city',
                'province',
                'address',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'levels',
                'mode_online',
                'mode_offline',
                'verification_note',
                'badge',
            ])
            ->toArray();

        if (array_key_exists('levels', $profileUpdates) && is_array($profileUpdates['levels'])) {
            $profileUpdates['levels'] = array_values($profileUpdates['levels']);
        }

        if (! empty($profileUpdates)) {
            $tutorProfile->update($profileUpdates);
        }

        return new TutorProfileResource($tutorProfile->fresh(['user']));
    }

    public function destroy(Request $request, TutorProfile $tutorProfile)
    {
        if ($tutorProfile->trashed()) {
            return response()->json(['message' => 'Tutor sudah dihapus.'], 422);
        }

        if ($request->boolean('force')) {
            $tutorProfile->forceDelete();
        } else {
            $tutorProfile->delete();
        }

        return response()->json(['message' => 'Tutor berhasil dihapus.']);
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

        Notification::sendNow($tutorProfile->user, new TutorVerificationNotification($tutorProfile), ['database', 'mail', WhatsAppChannel::class]);

        return new TutorProfileResource($tutorProfile->fresh());
    }
}
