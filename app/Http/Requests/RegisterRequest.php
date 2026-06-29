<?php
/**
 * FILE: backend/app/Http/Requests/RegisterRequest.php
 * STATUS: DIUBAH (ditandai deprecated via komentar saja, isi tidak berubah)
 */


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @deprecated Register email+password sudah tidak dipakai lagi sesuai instruksi
 * (diganti Google OAuth + Phone OTP, role default selalu 'siswa'). Class ini
 * dipertahankan agar tidak menghapus kode yang sudah ada, namun tidak lagi
 * dirujuk oleh routes/api.php. Lihat AuthController baru.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['siswa', 'tutor'])],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar, silakan login.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}
