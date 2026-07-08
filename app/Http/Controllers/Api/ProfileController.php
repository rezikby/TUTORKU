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
use Illuminate\Support\Facades\Http;

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
            'education_level' => ['sometimes', 'nullable', 'string', 'max:50'],
            'education_detail' => ['sometimes', 'nullable', 'string', 'max:50'],
            'onboarding_completed' => ['sometimes', 'boolean'],
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
            'tutor_profile.latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'tutor_profile.longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
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
                    $url = $profileData['google_maps_url'];
                    $profile->update(['google_maps_url' => $url]);
                    
                    // Auto-extract coordinates from Google Maps URL
                    $coords = $this->extractCoordsFromGoogleMapsUrl($url);
                    if ($coords) {
                        // Reject (0,0) placeholder
                        if (!($coords['lat'] == 0 && $coords['lng'] == 0)) {
                            $profile->update([
                                'latitude' => $coords['lat'],
                                'longitude' => $coords['lng'],
                            ]);
                        } else {
                            Log::warning('ProfileController: Google Maps URL contains placeholder (0,0)', [
                                'profile_id' => $profile->id,
                                'url' => $url,
                            ]);
                        }
                    }
                }
                if (array_key_exists('latitude', $profileData) || array_key_exists('longitude', $profileData)) {
                    $lat = array_key_exists('latitude', $profileData) ? $profileData['latitude'] : $profile->latitude;
                    $lon = array_key_exists('longitude', $profileData) ? $profileData['longitude'] : $profile->longitude;
                    
                    // Reject (0,0) placeholder for offline mode tutors
                    if ($profile->mode_offline && $lat == 0 && $lon == 0) {
                        return response()->json([
                            'message' => 'Tutor dengan mode offline tidak boleh memiliki koordinat (0,0)',
                            'errors' => [
                                'coordinates' => ['Silakan set koordinat yang valid']
                            ]
                        ], 422);
                    }
                    
                    $profile->update([ 'latitude' => $lat, 'longitude' => $lon ]);

                    try {
                        if ($lat !== null && $lon !== null) {
                            $res = Http::withHeaders(['User-Agent' => 'Tutorku/1.0'])->get('https://nominatim.openstreetmap.org/reverse', [
                                'format' => 'jsonv2',
                                'lat' => $lat,
                                'lon' => $lon,
                                'addressdetails' => 1,
                            ]);
                            if ($res->successful()) {
                                $json = $res->json();
                                $addr = $json['address'] ?? null;
                                if ($addr) {
                                    $profile->update([
                                        'city' => $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? $profile->city,
                                        'province' => $addr['state'] ?? $profile->province,
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
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

    /**
     * Extract latitude and longitude from Google Maps URL
     * Supports formats like:
     * - https://maps.google.com/?q=37.7749,-122.4194
     * - https://www.google.com/maps/search/?api=1&query=37.7749,-122.4194
     * - https://www.google.com/maps/place/@-1.85172,106.13191,...
     */
    private function extractCoordsFromGoogleMapsUrl(?string $url): ?array
    {
        if (!$url) {
            return null;
        }

        try {
            // Try to extract from @lat,lng pattern (like in place URLs)
            if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                ];
            }

            // Try to extract from !3d lat !4d lng pattern (alternative format)
            if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $matches)) {
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                ];
            }

            // Try to extract from query parameter or comma-separated format
            if (preg_match('/query=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                ];
            }

            // Try to extract from q parameter
            if (preg_match('/[?&]q=(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
                return [
                    'lat' => (float) $matches[1],
                    'lng' => (float) $matches[2],
                ];
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Failed to extract coords from Google Maps URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}