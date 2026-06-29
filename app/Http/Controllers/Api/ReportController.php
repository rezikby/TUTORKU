<?php
/**
 * FILE: backend/app/Http/Controllers/Api/ReportController.php
 * STATUS: DIUBAH (tambah kategori, cegah self-report & duplikasi)
 */


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REPORT TUTOR & konten lain (forum_post, forum_comment, user, tutor_profile).
 * Kategori sesuai instruksi: Penipuan, Spam, Konten Tidak Sesuai, Pelecehan, Lainnya.
 * Masuk ke dashboard admin (Admin\ReportController::index/resolve).
 */
class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportable_type' => ['required', Rule::in(['forum_post', 'forum_comment', 'user', 'tutor_profile'])],
            'reportable_id' => ['required', 'integer'],
            'category' => ['required', Rule::in(['penipuan', 'spam', 'konten_tidak_sesuai', 'pelecehan', 'lainnya'])],
            'reason' => ['nullable', 'required_if:category,lainnya', 'string', 'max:1000'],
        ]);

        $map = [
            'forum_post' => \App\Models\ForumPost::class,
            'forum_comment' => \App\Models\ForumComment::class,
            'user' => \App\Models\User::class,
            'tutor_profile' => \App\Models\TutorProfile::class,
        ];

        $modelClass = $map[$validated['reportable_type']];
        $target = $modelClass::findOrFail($validated['reportable_id']);

        if ($validated['reportable_type'] === 'user' && $target->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak dapat melaporkan diri sendiri.'], 422);
        }

        $alreadyReported = Report::where('reporter_id', $request->user()->id)
            ->where('reportable_type', $modelClass)
            ->where('reportable_id', $target->id)
            ->where('status', 'open')
            ->exists();

        if ($alreadyReported) {
            return response()->json([
                'message' => 'Kamu sudah melaporkan konten/pengguna ini sebelumnya dan masih dalam peninjauan admin.',
            ], 422);
        }

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $target->id,
            'category' => $validated['category'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => 'Laporan kamu telah dikirim ke admin untuk ditinjau.',
            'data' => $report,
        ], 201);
    }
}
