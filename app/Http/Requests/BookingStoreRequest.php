<?php
/**
 * FILE: backend/app/Http/Requests/BookingStoreRequest.php
 * STATUS: DIUBAH (tambah validasi lokasi & method pembayaran)
 */


namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookingStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tutor_profile_id' => ['required', 'exists:tutor_profiles,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i', 'regex:/^[0-2]\d:(00|10|20|30|40|50)$/'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'multiple_of:10'],
            'mode' => ['required', Rule::in(['online', 'offline'])],

            // Lokasi offline sekarang bisa menggunakan titik lokasi geolokasi saja.
            'location_address' => ['nullable', 'string', 'max:500'],
            'location_city' => ['nullable', 'string', 'max:100'],
            'location_province' => ['nullable', 'string', 'max:100'],
            'location_latitude' => ['required_if:mode,offline', 'numeric', 'between:-90,90'],
            'location_longitude' => ['required_if:mode,offline', 'numeric', 'between:-180,180'],
            'location_note' => ['nullable', 'string', 'max:500'],

            'notes' => ['nullable', 'string', 'max:1000'],
            'gateway' => ['nullable', Rule::in(['midtrans', 'xendit'])],
            'method' => ['nullable', Rule::in(['qris', 'ovo', 'dana', 'gopay', 'shopeepay', 'virtual_account', 'bank_transfer', 'cod'])],
        ];
    }

    public function messages(): array
    {
        return [
            'location_address.required_if' => 'Alamat lengkap wajib diisi untuk metode belajar offline.',
            'location_city.required_if' => 'Kota wajib diisi untuk metode belajar offline.',
            'location_province.required_if' => 'Provinsi wajib diisi untuk metode belajar offline.',
            'location_latitude.required_if' => 'Titik lokasi (Google Maps) wajib diisi untuk metode belajar offline.',
            'location_longitude.required_if' => 'Titik lokasi (Google Maps) wajib diisi untuk metode belajar offline.',
        ];
    }
}
