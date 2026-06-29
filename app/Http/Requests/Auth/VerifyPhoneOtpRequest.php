<?php
/**
 * FILE: backend/app/Http/Requests/Auth/VerifyPhoneOtpRequest.php
 * STATUS: BARU
 */


namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:15'],
            'code' => ['required', 'string', 'size:5'],
            'remember' => ['nullable', 'boolean'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
