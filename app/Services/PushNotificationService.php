<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk mengirim push notification via Firebase Cloud Messaging (FCM).
 */
class PushNotificationService
{
    private string $fcmServerKey;
    private string $fcmUrl = 'https://fcm.googleapis.com/fcm/send';

    public function __construct()
    {
        $this->fcmServerKey = config('services.firebase.server_key') ?? '';
    }

    /**
     * Kirim notifikasi push ke user berdasarkan FCM tokens yang terdaftar.
     */
    public function sendToUser(User $user, string $title, string $body, ?array $data = null): bool
    {
        if (!$this->fcmServerKey) {
            Log::warning('Firebase server key tidak dikonfigurasi');
            return false;
        }

        $tokens = $user->fcmTokens()->pluck('token')->toArray();
        if (empty($tokens)) {
            Log::debug("User {$user->id} tidak memiliki FCM token");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Kirim notifikasi push ke multiple tokens.
     */
    public function sendToTokens(array $tokens, string $title, string $body, ?array $data = null): bool
    {
        if (!$this->fcmServerKey) {
            Log::warning('Firebase server key tidak dikonfigurasi');
            return false;
        }

        if (empty($tokens)) {
            return false;
        }

        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ],
        ];

        if ($data) {
            $payload['data'] = $data;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->fcmServerKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->failed()) {
                Log::warning('FCM request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $result = $response->json();
            if (($result['failure'] ?? 0) > 0) {
                Log::warning('FCM: beberapa token gagal', $result);
                // Hapus invalid tokens
                $this->removeInvalidTokens($result);
            }

            return ($result['success'] ?? 0) > 0;
        } catch (\Exception $e) {
            Log::error('FCM error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus tokens yang tidak valid dari hasil FCM response.
     */
    private function removeInvalidTokens(array $response): void
    {
        if (!isset($response['results']) || !is_array($response['results'])) {
            return;
        }

        $invalidTokens = [];
        foreach ($response['results'] as $index => $result) {
            if (isset($result['error']) && in_array($result['error'], [
                'InvalidRegistration',
                'NotRegistered',
                'InvalidApnsSecret',
            ])) {
                if (isset($response['registration_ids'][$index])) {
                    $invalidTokens[] = $response['registration_ids'][$index];
                }
            }
        }

        if ($invalidTokens) {
            UserFcmToken::whereIn('token', $invalidTokens)->delete();
        }
    }

    /**
     * Register FCM token untuk user.
     */
    public function registerToken(User $user, string $token, ?string $deviceName = null, ?string $deviceType = null): UserFcmToken
    {
        return UserFcmToken::updateOrCreate(
            ['user_id' => $user->id, 'token' => $token],
            ['device_name' => $deviceName, 'device_type' => $deviceType]
        );
    }

    /**
     * Unregister FCM token.
     */
    public function unregisterToken(string $token): bool
    {
        return UserFcmToken::where('token', $token)->delete() > 0;
    }
}
