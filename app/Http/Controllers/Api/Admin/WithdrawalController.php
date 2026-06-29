<?php
/**
 * FILE: backend/app/Http/Controllers/Api/Admin/WithdrawalController.php
 * STATUS: BARU
 */


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdrawal::query()->with(['tutorProfile.user']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $withdrawals = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($withdrawals);
    }

    public function updateStatus(Request $request, Withdrawal $withdrawal)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['processing', 'completed', 'rejected'])],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        // Pencairan yang sudah final (completed/rejected) tidak boleh diubah lagi statusnya,
        // supaya saldo tutor tidak salah dikembalikan/dipotong dua kali secara keliru.
        if (in_array($withdrawal->status, ['completed', 'rejected'], true)) {
            return response()->json([
                'message' => 'Pencairan dana ini sudah final dan tidak dapat diubah lagi.',
            ], 422);
        }

        // Jika ditolak, kembalikan saldo ke tutor.
        if ($validated['status'] === 'rejected') {
            $withdrawal->tutorProfile->increment('balance', $withdrawal->amount);
        }

        $withdrawal->update([
            ...$validated,
            'processed_at' => in_array($validated['status'], ['completed', 'rejected'], true) ? now() : null,
        ]);

        return response()->json($withdrawal->fresh(['tutorProfile.user']));
    }
}
