<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FcmTokenController extends Controller
{
    public function __construct(
        private PushNotificationService $pushService
    ) {}

    /**
     * POST /api/fcm-tokens
     * Register FCM token untuk push notification.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:ios,android,web'],
        ]);

        $user = $request->user();
        $token = $this->pushService->registerToken(
            $user,
            $validated['token'],
            $validated['device_name'] ?? null,
            $validated['device_type'] ?? 'web'
        );

        return response()->json([
            'message' => 'FCM token berhasil didaftarkan',
            'data' => $token,
        ], 201);
    }

    /**
     * DELETE /api/fcm-tokens/{token}
     * Unregister FCM token.
     */
    public function unregister(Request $request, string $token): JsonResponse
    {
        $deleted = $this->pushService->unregisterToken($token);

        if (!$deleted) {
            return response()->json([
                'message' => 'Token tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'FCM token berhasil dihapus',
        ]);
    }

    /**
     * GET /api/fcm-tokens
     * Daftar semua FCM tokens user.
     */
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $user->fcmTokens()
            ->select('id', 'token', 'device_name', 'device_type', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $tokens,
            'count' => $tokens->count(),
        ]);
    }

    /**
     * DELETE /api/fcm-tokens
     * Hapus semua FCM tokens user.
     */
    public function deleteAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $deletedCount = $user->fcmTokens()->delete();

        return response()->json([
            'message' => "Berhasil menghapus {$deletedCount} FCM token",
            'deleted_count' => $deletedCount,
        ]);
    }
}
