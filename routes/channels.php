<?php

use App\Models\Booking;
use App\Models\ChatConversation;
use App\Models\LiveSession;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Channel notifikasi privat per user (dipakai otomatis oleh Laravel Notifications)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel chat - hanya 2 partisipan percakapan yang boleh mendengarkan
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    $conversation = ChatConversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    return in_array($user->id, [$conversation->user_one_id, $conversation->user_two_id], true);
});

Broadcast::channel('chat-messages.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Presence channel untuk Live Class (WebRTC signaling + Whiteboard realtime)
// Hanya siswa & tutor yang terlibat di booking terkait room ini yang boleh join.
Broadcast::channel('live-session.{roomId}', function ($user, $roomId) {
    $session = LiveSession::where('room_id', $roomId)->with('booking.tutorProfile')->first();

    if (! $session) {
        return false;
    }

    $booking = $session->booking;
    $allowed = $user->id === $booking->student_id || $user->id === $booking->tutorProfile->user_id || $user->role === 'admin';

    if (! $allowed) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar_url,
        'role' => $user->role,
        'isAudioOn' => true,
        'isVideoOn' => true,
        'isScreenSharing' => false,
        'isSpeaking' => false,
    ];
});
