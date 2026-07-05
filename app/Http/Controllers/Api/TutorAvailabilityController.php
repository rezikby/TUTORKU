<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorAvailabilityResource;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TutorAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        return TutorAvailabilityResource::collection(
            $profile->availabilities()
                ->with('subject')
                ->orderBy('date')
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        // Normalize start_time/end_time (accept e.g. "06:00 PM") to 24-hour H:i
        if ($request->filled('start_time')) {
            try {
                $parsed = Carbon::parse($request->input('start_time'));
                $request->merge(['start_time' => $parsed->format('H:i')]);
            } catch (\Throwable $e) {
                // leave as-is; validation will catch format problems
            }
        }
        if ($request->filled('end_time')) {
            try {
                $parsed = Carbon::parse($request->input('end_time'));
                $request->merge(['end_time' => $parsed->format('H:i')]);
            } catch (\Throwable $e) {
                // leave as-is
            }
        }

        // Accept H:i and H:i:s (allow any minute 00-59 and optional seconds)
        $timeRegex = '/^([01]\\d|2[0-3]):([0-5]\\d)(:([0-5]\\d))?$/';
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'day_of_week' => ['required_without:date', 'integer', 'between:0,6'],
            // Accept H:i and H:i:s (some browsers/platforms include seconds)
            'start_time' => ['required', 'regex:'.$timeRegex],
            'end_time' => ['required', 'regex:'.$timeRegex],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        // Ensure end_time is after start_time (compare times using Carbon)
        try {
            $start = Carbon::parse($validated['start_time']);
            $end = Carbon::parse($validated['end_time']);
            if (! $end->greaterThan($start)) {
                return response()->json([
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors' => ['end_time' => ['Waktu selesai harus setelah waktu mulai.']],
                ], 422);
            }
        } catch (\Throwable $e) {
            // If parsing fails, let validation handle it (shouldn't happen because of regex)
        }

        if (!empty($validated['date'])) {
            $validated['day_of_week'] = Carbon::parse($validated['date'])->dayOfWeek;
        }

        if (! $profile->subjects()->where('subjects.id', $validated['subject_id'])->exists()) {
            return response()->json(['message' => 'Mapel tidak valid untuk jadwal ini.'], 422);
        }

        $availability = $profile->availabilities()->create($validated);

        return new TutorAvailabilityResource($availability->fresh('subject'));
    }

    public function update(Request $request, int $availability)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $item = $profile->availabilities()->findOrFail($availability);
        // Normalize times if provided (allow AM/PM inputs from browser)
        if ($request->filled('start_time')) {
            try {
                $parsed = Carbon::parse($request->input('start_time'));
                $request->merge(['start_time' => $parsed->format('H:i')]);
            } catch (\Throwable $e) {
            }
        }
        if ($request->filled('end_time')) {
            try {
                $parsed = Carbon::parse($request->input('end_time'));
                $request->merge(['end_time' => $parsed->format('H:i')]);
            } catch (\Throwable $e) {
            }
        }

        // Accept H:i and H:i:s (allow any minute 00-59 and optional seconds)
        $timeRegex = '/^([01]\\d|2[0-3]):([0-5]\\d)(:([0-5]\\d))?$/';
        $validated = $request->validate([
            'date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'day_of_week' => ['sometimes', 'integer', 'between:0,6'],
            'start_time' => ['sometimes', 'regex:'.$timeRegex],
            'end_time' => ['sometimes', 'regex:'.$timeRegex],
            'is_active' => ['sometimes', 'boolean'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
        ]);

        // If both times provided, ensure end_time is after start_time
        if (array_key_exists('start_time', $validated) && array_key_exists('end_time', $validated)) {
            try {
                $start = Carbon::parse($validated['start_time']);
                $end = Carbon::parse($validated['end_time']);
                if (! $end->greaterThan($start)) {
                    return response()->json([
                        'message' => 'Data yang dikirim tidak valid.',
                        'errors' => ['end_time' => ['Waktu selesai harus setelah waktu mulai.']],
                    ], 422);
                }
            } catch (\Throwable $e) {
                // ignore, validation will catch format issues
            }
        }

        if (array_key_exists('date', $validated) && !empty($validated['date'])) {
            $validated['day_of_week'] = Carbon::parse($validated['date'])->dayOfWeek;
        }

        if (array_key_exists('subject_id', $validated) && ! $profile->subjects()->where('subjects.id', $validated['subject_id'])->exists()) {
            return response()->json(['message' => 'Mapel tidak valid untuk jadwal ini.'], 422);
        }

        $item->update($validated);

        return new TutorAvailabilityResource($item->fresh('subject'));
    }

    public function destroy(Request $request, int $availability)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();
        $profile->availabilities()->findOrFail($availability)->delete();

        return response()->json(['message' => 'Jadwal berhasil dihapus.']);
    }
}
