<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Report::query()->with(['reporter', 'reportable', 'handledBy']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $reports = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($reports);
    }

    public function resolve(Request $request, Report $report)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'action_taken', 'dismissed'])],
            'action' => ['nullable', Rule::in(['suspend_permanent', 'suspend_temporary', 'dismiss'])],
            'duration_hours' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['status'] === 'action_taken' && in_array($validated['action'], ['suspend_permanent', 'suspend_temporary'], true)) {
            $target = $report->reportable;
            $suspendedData = ['status' => 'suspended'];

            if ($validated['action'] === 'suspend_temporary' && ! empty($validated['duration_hours'])) {
                $suspendedData['suspended_until'] = now()->addHours($validated['duration_hours']);
            } else {
                $suspendedData['suspended_until'] = null;
            }

            if ($target instanceof \App\Models\User) {
                $target->update($suspendedData);
            } elseif ($target instanceof \App\Models\TutorProfile) {
                $target->user->update($suspendedData);
            }
        }

        $report->update([
            'status' => $validated['status'],
            'handled_by' => $request->user()->id,
            'handled_note' => $validated['note'] ?? null,
        ]);

        return response()->json($report->fresh(['reporter', 'reportable', 'handledBy']));
    }
}
