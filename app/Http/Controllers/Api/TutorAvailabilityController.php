<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorAvailabilityResource;
use Illuminate\Http\Request;

class TutorAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        return TutorAvailabilityResource::collection(
            $profile->availabilities()->orderBy('day_of_week')->orderBy('start_time')->get()
        );
    }

    public function store(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $availability = $profile->availabilities()->create($validated);

        return new TutorAvailabilityResource($availability);
    }

    public function update(Request $request, int $availability)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $item = $profile->availabilities()->findOrFail($availability);

        $validated = $request->validate([
            'day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $item->update($validated);

        return new TutorAvailabilityResource($item->fresh());
    }

    public function destroy(Request $request, int $availability)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $profile->availabilities()->findOrFail($availability)->delete();

        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }
}
