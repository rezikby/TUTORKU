<?php

namespace App\Http\Controllers\Api;

use App\Models\TutorProfile;
use Illuminate\Http\Request;

/**
 * Debug controller - HANYA untuk development
 * Hapus di production!
 */
class DebugController extends Controller
{
    /**
     * GET /api/debug/tutors-coordinates
     * Menampilkan semua koordinat tutor untuk diagnosis
     */
    public function tutorCoordinates(Request $request)
    {
        // Safety check - hanya izinkan di development atau untuk admin
        if (app()->environment('production') && !$request->user()?->isAdmin()) {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        $tutors = TutorProfile::verified()
            ->with('user:id,name,email')
            ->select(['id', 'user_id', 'latitude', 'longitude', 'city', 'mode_offline'])
            ->orderBy('id')
            ->get()
            ->map(function ($tutor) {
                $hasCoords = $tutor->latitude !== null && $tutor->longitude !== null;
                $isValid = $hasCoords && 
                          $tutor->latitude >= -90 && $tutor->latitude <= 90 &&
                          $tutor->longitude >= -180 && $tutor->longitude <= 180;
                
                return [
                    'id' => $tutor->id,
                    'name' => $tutor->user->name ?? 'N/A',
                    'latitude' => $tutor->latitude,
                    'longitude' => $tutor->longitude,
                    'city' => $tutor->city,
                    'mode_offline' => $tutor->mode_offline,
                    'has_coordinates' => $hasCoords,
                    'is_valid' => $isValid,
                    'status' => $hasCoords ? ($isValid ? '✓ VALID' : '✗ INVALID') : '∅ MISSING',
                ];
            });

        $stats = [
            'total' => $tutors->count(),
            'with_valid_coords' => $tutors->where('is_valid', true)->count(),
            'with_invalid_coords' => $tutors->where('has_coordinates', true)->where('is_valid', false)->count(),
            'without_coords' => $tutors->where('has_coordinates', false)->count(),
        ];

        return response()->json([
            'stats' => $stats,
            'tutors' => $tutors,
        ]);
    }

    /**
     * GET /api/debug/tutor/{id}
     * Menampilkan detail lengkap koordinat satu tutor
     */
    public function tutorDetail(Request $request, $tutorId)
    {
        if (app()->environment('production') && !$request->user()?->isAdmin()) {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        $tutor = TutorProfile::with('user')
            ->findOrFail($tutorId);

        $lat = $tutor->latitude;
        $lng = $tutor->longitude;
        $hasCoords = $lat !== null && $lng !== null;
        $isValid = $hasCoords && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        return response()->json([
            'tutor' => [
                'id' => $tutor->id,
                'name' => $tutor->user->name,
                'email' => $tutor->user->email,
                'latitude' => $lat,
                'longitude' => $lng,
                'city' => $tutor->city,
                'address' => $tutor->address,
                'mode_offline' => $tutor->mode_offline,
                'mode_online' => $tutor->mode_online,
                'created_at' => $tutor->created_at,
                'updated_at' => $tutor->updated_at,
            ],
            'diagnosis' => [
                'has_coordinates' => $hasCoords,
                'is_valid' => $isValid,
                'latitude_valid' => $hasCoords ? ($lat >= -90 && $lat <= 90 ? '✓' : '✗') : 'NULL',
                'longitude_valid' => $hasCoords ? ($lng >= -180 && $lng <= 180 ? '✓' : '✗') : 'NULL',
                'message' => $this->getDiagnosisMessage($lat, $lng, $hasCoords, $isValid),
            ],
        ]);
    }

    private function getDiagnosisMessage($lat, $lng, $hasCoords, $isValid)
    {
        if (!$hasCoords) {
            return 'Tutor belum memiliki koordinat. Minta tutor untuk menambahkan Google Maps URL saat registrasi atau update profil.';
        }

        if (!$isValid) {
            $messages = [];
            if ($lat < -90 || $lat > 90) {
                $messages[] = "Latitude {$lat} out of range (-90 to 90)";
            }
            if ($lng < -180 || $lng > 180) {
                $messages[] = "Longitude {$lng} out of range (-180 to 180)";
            }
            return implode('; ', $messages) . '. Koordinat ini akan menyebabkan jarak salah hingga ribuan km!';
        }

        return 'Koordinat valid. Harusnya jarak dihitung dengan benar.';
    }
}
