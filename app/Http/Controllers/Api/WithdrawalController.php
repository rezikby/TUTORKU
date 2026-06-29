<?php
/**
 * FILE: backend/app/Http/Controllers/Api/WithdrawalController.php
 * STATUS: BARU
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PENCAIRAN DANA — tutor menarik saldo (tutor_profiles.balance) hasil
 * sesi yang sudah completed, ke rekening bank yang didaftarkan saat
 * Pengajuan Tutor (bisa diubah saat pengajuan penarikan).
 */
class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $withdrawals = $profile->withdrawals()->latest()->paginate($request->integer('per_page', 10));

        return response()->json($withdrawals);
    }

    public function store(Request $request)
    {
        $profile = $request->user()->tutorProfile()->firstOrFail();

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:50000'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder' => ['required', 'string', 'max:150'],
        ]);

        try {
            $withdrawal = DB::transaction(function () use ($profile, $validated) {
                // Lock baris tutor_profiles selama transaksi supaya dua pengajuan
                // pencairan yang nyaris bersamaan tidak bisa sama-sama lolos
                // pengecekan saldo dan menyebabkan saldo minus (double-spending).
                $lockedProfile = $profile->newQuery()->lockForUpdate()->find($profile->id);

                if ($validated['amount'] > $lockedProfile->balance) {
                    throw new \RuntimeException('Saldo kamu tidak cukup untuk pencairan ini.');
                }

                $lockedProfile->decrement('balance', $validated['amount']);

                return $lockedProfile->withdrawals()->create($validated);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pengajuan pencairan dana berhasil dikirim. Dana akan diproses dalam 1-3 hari kerja.',
            'data' => $withdrawal,
        ], 201);
    }
}
