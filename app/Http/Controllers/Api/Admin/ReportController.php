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
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report->update([
            'status' => $validated['status'],
            'handled_by' => $request->user()->id,
            'handled_note' => $validated['note'] ?? null,
        ]);

        return response()->json($report->fresh(['reporter', 'reportable', 'handledBy']));
    }
}
