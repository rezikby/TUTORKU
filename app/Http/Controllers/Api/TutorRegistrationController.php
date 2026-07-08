<?php
/**
 * FILE: backend/app/Http/Controllers/Api/TutorRegistrationController.php
 * STATUS: DIUBAH TOTAL (siswa->tutor, KTP+selfie, reCAPTCHA)
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorProfileResource;
use App\Services\Auth\RecaptchaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stepper PENGAJUAN TUTOR (instruksi):
 * 1 Akun (otomatis, dibuat saat user pertama kali menekan "Daftar Jadi Tutor")
 * 2 Data Diri   3 Pendidikan   4 Dokumen & Verifikasi   5 Submit
 *
 * Catatan penting: semua akun baru otomatis berstatus 'siswa'. Endpoint-endpoint
 * di sini diakses oleh siswa yang ingin mengajukan diri jadi tutor (middleware
 * role:siswa di routes/api.php), BUKAN oleh role tutor. Begitu admin approve,
 * role user berubah jadi 'tutor' (lihat Admin\TutorVerificationController::approve()).
 */
class TutorRegistrationController extends Controller
{
    public function __construct(protected RecaptchaService $recaptcha)
    {
    }

    /** POST /api/tutor/registration/start — siswa memulai pengajuan tutor (membuat TutorProfile kosong). */
    public function start(Request $request)
    {
        $user = $request->user();

        $profile = $user->tutorProfile;

        if ($profile) {
            return new TutorProfileResource($profile->load(['subjects', 'educations', 'experiences', 'certificates']));
        }

        $profile = $user->tutorProfile()->create([
            'registration_step' => 1,
        ]);

        return new TutorProfileResource($profile);
    }

    public function show(Request $request)
    {
        $profile = $request->user()->tutorProfile()->with(['subjects', 'educations', 'experiences', 'certificates'])->first();

        if (! $profile) {
            return response()->json([
                'message' => 'Kamu belum memulai pengajuan tutor. Mulai dari step 1.',
            ], 404);
        }

        return new TutorProfileResource($profile);
    }

    /** STEP 2 — Data Diri (termasuk Nama Lengkap & Foto Profil sesuai instruksi). */
    public function step2(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $this->ensureEditable($profile);

        $validated = $request->validate([
            'headline' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'bio' => ['required', 'string', 'max:2000'],
            'price_per_hour' => ['required', 'integer', 'min:10000'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'google_maps_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => [Rule::in(['SD', 'SMP', 'SMA', 'Mahasiswa'])],
            'mode_online' => ['required', 'boolean'],
            'mode_offline' => ['required', 'boolean'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['exists:subjects,id'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $updates = [
            ...collect($validated)->except(['subject_ids', 'profile_photo', 'email'])->all(),
            'registration_step' => max($profile->registration_step, 3),
        ];

        // Auto-extract coordinates from Google Maps URL if provided
        if (!empty($validated['google_maps_url'])) {
            $coords = $this->extractCoordsFromGoogleMapsUrl($validated['google_maps_url']);
            if ($coords) {
                $updates['latitude'] = $coords['lat'];
                $updates['longitude'] = $coords['lng'];
            }
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('tutor/profile-photos', 'public');
            $updates['profile_photo_path'] = $path;

            try {
                $fullPath = storage_path('app/public/' . $path);
                if (file_exists($fullPath)) {
                    $gps = $this->getGpsFromImage($fullPath);
                    if ($gps) {
                        $updates['latitude'] = $gps['lat'];
                        $updates['longitude'] = $gps['lon'];

                        $addr = $this->reverseGeocode($gps['lat'], $gps['lon']);
                        if ($addr) {
                            $updates['city'] = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? null;
                            $updates['province'] = $addr['state'] ?? $updates['province'] ?? null;
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignore failures silently
            }
        }

        $profile->update($updates);
        
        // Update User email untuk login tutor nantinya
        $request->user()->update(['email' => $validated['email']]);
        
        $profile->subjects()->sync($validated['subject_ids']);

        return new TutorProfileResource($profile->fresh(['subjects']));
    }

    /** STEP 3 — Pendidikan & Pengalaman */
    public function step3(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $this->ensureEditable($profile);

        $validated = $request->validate([
            'educations' => ['required', 'array', 'min:1'],
            'educations.*.degree' => ['required', 'string', 'max:100'],
            'educations.*.institution' => ['required', 'string', 'max:255'],
            'educations.*.major' => ['nullable', 'string', 'max:255'],
            'educations.*.year_start' => ['nullable', 'integer'],
            'educations.*.year_end' => ['nullable', 'integer'],
            'experiences' => ['nullable', 'array'],
            'experiences.*.title' => ['required_with:experiences', 'string', 'max:255'],
            'experiences.*.institution' => ['nullable', 'string', 'max:255'],
            'experiences.*.description' => ['nullable', 'string', 'max:1000'],
            'experiences.*.year_start' => ['nullable', 'integer'],
            'experiences.*.year_end' => ['nullable', 'integer'],
        ]);

        $profile->educations()->delete();
        $profile->educations()->createMany($validated['educations']);

        $profile->experiences()->delete();
        $profile->experiences()->createMany($validated['experiences'] ?? []);

        $profile->update(['registration_step' => max($profile->registration_step, 4)]);

        return new TutorProfileResource($profile->fresh(['educations', 'experiences']));
    }

    /**
     * STEP 4 — Dokumen & Verifikasi.
     * Sesuai instruksi: Foto KTP, Selfie dengan KTP (terpisah), CV PDF, Sertifikat PDF,
     * Video Perkenalan, Rekening Bank. Dilindungi Google reCAPTCHA + validasi file ketat.
     */
    public function step4(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $this->ensureEditable($profile);

        if (! $this->recaptcha->verify($request->input('recaptcha_token'), $request->ip())) {
            return response()->json([
                'message' => 'Verifikasi reCAPTCHA gagal. Silakan coba lagi.',
            ], 422);
        }

        $validated = $request->validate([
            'certificates' => ['nullable', 'array'],
            'certificates.*.name' => ['required_with:certificates', 'string', 'max:255'],
            'certificates.*.file' => ['required_with:certificates', 'file', 'mimes:pdf', 'max:5120'],
            'certificates.*.issued_by' => ['nullable', 'string', 'max:255'],
            'certificates.*.issued_year' => ['nullable', 'integer'],
            'cv' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'ktp_photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:3072'],
            'selfie_ktp' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:3072'],
            'intro_video' => ['nullable', 'file', 'mimes:mp4,mov,webm', 'max:51200'],
            'intro_video_url' => ['nullable', 'url'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'max:150'],
        ]);

        foreach ($validated['certificates'] ?? [] as $cert) {
            $profile->certificates()->create([
                'name' => $cert['name'],
                'file_path' => $cert['file']->store('tutor/certificates', 'public'),
                'issued_by' => $cert['issued_by'] ?? null,
                'issued_year' => $cert['issued_year'] ?? null,
            ]);
        }

        $updates = [
            'registration_step' => max($profile->registration_step, 5),
            'bank_name' => $validated['bank_name'],
            'bank_account_number' => $validated['bank_account_number'],
            'bank_account_holder' => $validated['bank_account_holder'],
            'cv_path' => $request->file('cv')->store('tutor/cv', 'public'),
            'ktp_photo_path' => $request->file('ktp_photo')->store('tutor/ktp', 'public'),
            'selfie_ktp_path' => $request->file('selfie_ktp')->store('tutor/selfie-ktp', 'public'),
        ];

        if ($request->hasFile('intro_video')) {
            $updates['intro_video_path'] = $request->file('intro_video')->store('tutor/videos', 'public');
        }
        if (isset($validated['intro_video_url'])) {
            $updates['intro_video_url'] = $validated['intro_video_url'];
        }

        $profile->update($updates);

        return new TutorProfileResource($profile->fresh(['certificates']));
    }

    /** STEP 5 — Submit pengajuan untuk diverifikasi admin. */
    public function submit(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        if ($profile->registration_step < 5) {
            return response()->json([
                'message' => 'Lengkapi semua tahap registrasi terlebih dahulu sebelum mengajukan verifikasi.',
            ], 422);
        }

        $profile->update([
            'registration_submitted' => true,
            'verification_status' => 'pending',
        ]);

        return new TutorProfileResource($profile->fresh());
    }

    /**
     * Mencegah perubahan data pengajuan setelah disubmit & sedang ditinjau/sudah
     * terverifikasi admin. Jika sebelumnya ditolak ('rejected'), tetap boleh
     * diedit supaya siswa bisa merevisi data sebelum mengajukan ulang.
     */
    protected function ensureEditable($profile): void
    {
        if ($profile->registration_submitted && $profile->verification_status !== 'rejected') {
            abort(422, 'Pengajuan kamu sedang ditinjau atau sudah disetujui, data tidak dapat diubah lagi.');
        }
    }

    private function getGpsFromImage(string $filePath): ?array
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($filePath);
        if (! $exif) {
            return null;
        }

        if (empty($exif['GPSLatitude']) || empty($exif['GPSLongitude'])) {
            return null;
        }

        $lat = $this->gpsToDecimal($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
        $lon = $this->gpsToDecimal($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');

        if ($lat === null || $lon === null) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    private function gpsToDecimal($coord, $ref)
    {
        try {
            $parts = $coord;
            $degrees = $this->evalRational($parts[0]);
            $minutes = $this->evalRational($parts[1]);
            $seconds = $this->evalRational($parts[2]);

            $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
            if (in_array(strtoupper($ref), ['S', 'W'])) {
                $decimal *= -1;
            }

            return $decimal;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function evalRational($r)
    {
        if (is_numeric($r)) {
            return (float) $r;
        }
        if (is_string($r) && strpos($r, '/')) {
            [$n, $d] = explode('/', $r);
            if ((float) $d === 0.0) {
                return 0.0;
            }
            return (float) $n / (float) $d;
        }
        if (is_array($r) && isset($r['0']) && isset($r['1'])) {
            return $this->evalRational($r[0]);
        }

        return 0.0;
    }

    private function reverseGeocode(float $lat, float $lon): ?array
    {
        try {
            $res = Http::withHeaders(['User-Agent' => 'Tutorku/1.0'])->get('https://nominatim.openstreetmap.org/reverse', [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lon,
                'addressdetails' => 1,
            ]);

            if (! $res->successful()) {
                return null;
            }

            $json = $res->json();
            return $json['address'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Upload profile photo only — used by frontend to extract GPS and prefill city/province.
     */
    public function uploadPhoto(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ]);

        $path = $request->file('profile_photo')->store('tutor/profile-photos', 'public');

        $updates = ['profile_photo_path' => $path];

        try {
            $fullPath = storage_path('app/public/' . $path);
            if (file_exists($fullPath)) {
                $gps = $this->getGpsFromImage($fullPath);
                if ($gps) {
                    $updates['latitude'] = $gps['lat'];
                    $updates['longitude'] = $gps['lon'];

                    $addr = $this->reverseGeocode($gps['lat'], $gps['lon']);
                    if ($addr) {
                        $updates['city'] = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? null;
                        $updates['province'] = $addr['state'] ?? null;
                    }
                }
            }
        } catch (\Exception $e) {
            // ignore
        }

        $profile->update($updates);

        return new TutorProfileResource($profile->fresh(['subjects', 'educations', 'experiences', 'certificates']));
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
