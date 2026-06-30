<?php
/**
 * FILE: backend/app/Http/Requests/Auth/SendPhoneOtpRequest.php
 * STATUS: BARU
 */


namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendPhoneOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:9', 'max:20', 'regex:/^[0-9+\s\-()]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, +, spasi, tanda hubung, atau tanda kurung.',
        ];
    }
}
