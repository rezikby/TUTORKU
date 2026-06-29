<?php
/**
 * FILE: backend/app/Http/Requests/Auth/VerifyGoogleOtpRequest.php
 * STATUS: BARU
 */


namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyGoogleOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pending_token' => ['required', 'string'],
            'code' => ['required', 'string', 'size:5'],
            'remember' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
