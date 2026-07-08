<?php
/**
 * Script untuk debug koordinat tutor
 * Jalankan dengan: php artisan tinker < debug_tutor_coords.php
 */

use App\Models\TutorProfile;
use Illuminate\Support\Facades\Log;

echo "\n=== DIAGNOSTIC: Tutor Coordinates ===\n\n";

// Ambil semua tutor yang terverifikasi
$tutors = TutorProfile::verified()
    ->with('user')
    ->get();

echo "Total verified tutors: " . $tutors->count() . "\n";
echo str_repeat("=", 60) . "\n\n";

$withCoords = 0;
$withoutCoords = 0;
$invalidCoords = 0;

foreach ($tutors as $tutor) {
    $lat = $tutor->latitude;
    $lng = $tutor->longitude;
    
    $hasCoords = $lat !== null && $lng !== null;
    $isValid = $hasCoords && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    
    if ($hasCoords && $isValid) {
        $withCoords++;
        echo "✓ {$tutor->id} | {$tutor->user->name} | Lat: {$lat}, Lng: {$lng}\n";
    } else if ($hasCoords && !$isValid) {
        $invalidCoords++;
        echo "✗ INVALID {$tutor->id} | {$tutor->user->name} | Lat: {$lat}, Lng: {$lng}\n";
    } else {
        $withoutCoords++;
        echo "∅ NO COORDS {$tutor->id} | {$tutor->user->name}\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Summary:\n";
echo "  ✓ With valid coordinates: {$withCoords}\n";
echo "  ✗ With invalid coordinates: {$invalidCoords}\n";
echo "  ∅ Without coordinates: {$withoutCoords}\n";
echo "\n";
