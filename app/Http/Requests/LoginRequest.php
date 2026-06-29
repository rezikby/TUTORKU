<?php
/**
 * FILE: backend/app/Http/Requests/LoginRequest.php
 * STATUS: DIUBAH (ditandai deprecated via komentar saja, isi tidak berubah)
 */


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @deprecated Login email+password sudah tidak dipakai lagi sesuai instruksi
 * (diganti Google OAuth + Phone OTP). Class ini dipertahankan agar tidak
 * menghapus kode yang sudah ada, namun tidak lagi dirujuk oleh routes/api.php.
 * Lihat App\Http\Requests\Auth\PhoneOtpRequest dan AuthController baru.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
