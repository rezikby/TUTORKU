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
            'bio' => ['required', 'string', 'max:2000'],
            'price_per_hour' => ['required', 'integer', 'min:10000'],
            'experience_years' => ['required', 'integer', 'min:0'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
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
            ...collect($validated)->except(['subject_ids', 'profile_photo'])->all(),
            'registration_step' => max($profile->registration_step, 3),
        ];

        if ($request->hasFile('profile_photo')) {
            $updates['profile_photo_path'] = $request->file('profile_photo')->store('tutor/profile-photos', 'public');
        }

        $profile->update($updates);
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
}
