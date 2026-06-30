<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ProfileUpdated;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return new UserResource($request->user()->load('settings', 'tutorProfile.subjects'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'gender' => ['sometimes', 'nullable', Rule::in(['L', 'P'])],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'intro_video' => ['sometimes', 'nullable', 'file', 'mimes:mp4,mov,webm', 'max:102400'],
            'tutor_profile' => ['sometimes', 'array'],
            'tutor_profile.bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'tutor_profile.headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tutor_profile.levels' => ['sometimes', 'array'],
            'tutor_profile.levels.*' => ['string', Rule::in(['SD', 'SMP', 'SMA', 'Mahasiswa'])],
            'tutor_profile.subject_ids' => ['sometimes', 'array'],
            'tutor_profile.subject_ids.*' => ['integer', 'exists:subjects,id'],
            'tutor_profile.google_maps_url' => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        if ($request->hasFile('intro_video') || $request->filled('tutor_profile') || $request->has('tutor_profile')) {
            $profileData = $request->input('tutor_profile', []);
            if (is_array($profileData)) {
                $profile = $user->tutorProfile()->first();

                $profileAttributes = [];

                if (array_key_exists('bio', $profileData)) {
                    $profileAttributes['bio'] = $profileData['bio'];
                }
                if (array_key_exists('headline', $profileData)) {
                    $profileAttributes['headline'] = $profileData['headline'];
                }
                if (array_key_exists('levels', $profileData)) {
                    $profileAttributes['levels'] = $profileData['levels'];
                }

                if (! $profile) {
                    $profileAttributes['user_id'] = $user->id;
                    $profile = $user->tutorProfile()->create($profileAttributes);
                } else {
                    if (! empty($profileAttributes)) {
                        $profile->update($profileAttributes);
                    }
                }

                if ($request->hasFile('intro_video')) {
                    if ($profile->intro_video_path) {
                        Storage::disk('public')->delete($profile->intro_video_path);
                    }
                    $profile->update([
                        'intro_video_path' => $request->file('intro_video')->store('tutor/videos', 'public'),
                    ]);
                }

                if (array_key_exists('subject_ids', $profileData)) {
                    $profile->subjects()->sync($profileData['subject_ids'] ?? []);
                }
                if (array_key_exists('google_maps_url', $profileData)) {
                    $profile->update(['google_maps_url' => $profileData['google_maps_url']]);
                }
            }
        }

        $fresh = $user->fresh()->load('settings', 'tutorProfile.subjects');

        // Broadcast event dibungkus try/catch agar kegagalan koneksi ke
        // server WebSocket (Reverb/Pusher) tidak menggagalkan response API.
        // Data profil di atas sudah berhasil disimpan terlepas dari hasil broadcast ini.
        try {
            event(new ProfileUpdated($fresh));
        } catch (\Throwable $e) {
            Log::warning('Gagal broadcast ProfileUpdated: ' . $e->getMessage());
        }

        return new UserResource($fresh);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password saat ini tidak sesuai.',
                'errors' => ['current_password' => ['Password saat ini tidak sesuai.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password berhasil diperbarui.']);
    }
}