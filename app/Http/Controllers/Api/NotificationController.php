<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->when($request->boolean('exclude_chat'), fn ($q) => $q->where('data->category', '!=', 'chat'))
            ->when($request->boolean('unread_only'), fn ($q) => $q->whereNull('read_at'))
            ->paginate($request->integer('per_page', 20));

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request)
    {
        $query = $request->user()->unreadNotifications();

        if ($request->boolean('exclude_chat')) {
            $query = $query->where('data->category', '!=', 'chat');
        }

        return response()->json([
            'unread_count' => $query->count(),
        ]);
    }

    public function markRead(Request $request, string $notificationId)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return response()->json(['message' => 'OK']);
    }

    public function markAllRead(Request $request)
    {
        $query = $request->user()->unreadNotifications();

        if ($request->boolean('exclude_chat')) {
            $query->where('data->category', '!=', 'chat');
        }

        $query->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    public function destroy(Request $request, string $notificationId)
    {
        $notification = $request->user()->notifications()->findOrFail($notificationId);
        $notification->delete();

        return response()->json(['message' => 'Notifikasi berhasil dihapus.']);
    }
}
