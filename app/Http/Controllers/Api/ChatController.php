<?php
/**
 * FILE: backend/app/Http/Controllers/Api/ChatController.php
 * STATUS: DIUBAH (tambah typing indicator, fix validasi self-chat, error handling broadcast)
 */

namespace App\Http\Controllers\Api;

// masujk

use App\Events\ChatMessageSent;
use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = ChatConversation::query()
            ->where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with(['userOne', 'userTwo', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->whereNull('read_at')->where('sender_id', '!=', $userId);
            }])
            ->orderByDesc('last_message_at')
            ->get();

        return ChatConversationResource::collection($conversations);
    }

    /** Mulai / ambil percakapan dengan user lain (misal dari halaman Detail Tutor). */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
        ]);

        $userId = $request->user()->id;
        $otherId = (int) $validated['user_id'];

        if ($otherId === $userId) {
            return response()->json(['message' => 'Tidak dapat membuat percakapan dengan diri sendiri.'], 422);
        }

        [$one, $two] = $userId < $otherId ? [$userId, $otherId] : [$otherId, $userId];

        $conversation = ChatConversation::firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['booking_id' => $validated['booking_id'] ?? null, 'last_message_at' => now()]
        );

        return new ChatConversationResource($conversation->load(['userOne', 'userTwo']));
    }

    public function messages(Request $request, ChatConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $userId = $request->user()->id;

        $messages = $conversation->messages()
            ->with('sender')
            ->where(function ($query) use ($userId) {
                $query
                    ->where('is_deleted', false)
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('is_deleted', true)
                            ->where('deleted_for', 'all');
                    })
                    ->orWhere(function ($q) use ($userId) {
                        $q->where('is_deleted', true)
                            ->where('deleted_for', 'me')
                            ->where('deleted_by_user_id', '!=', $userId);
                    });
            })
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return ChatMessageResource::collection($messages);
    }

    public function send(Request $request, ChatConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'file', 'voice'])],
            'content' => ['required_if:type,text', 'nullable', 'string', 'max:5000'],
            'file' => [
                'required_if:type,image,file,voice', 'nullable', 'file', 'max:10240',
                Rule::when($request->input('type') === 'image', ['mimes:jpg,jpeg,png,gif,webp']),
                Rule::when($request->input('type') === 'file', ['mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar']),
                Rule::when($request->input('type') === 'voice', ['mimes:mp3,wav,ogg,m4a,webm']),
            ],
            'duration_seconds' => ['nullable', 'integer'],
        ]);

        $data = [
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'type' => $validated['type'],
            'content' => $validated['content'] ?? null,
            'duration_seconds' => $validated['duration_seconds'] ?? null,
        ];

        if ($request->hasFile('file')) {
            $folder = match ($validated['type']) {
                'image' => 'chat/images',
                'voice' => 'chat/voice',
                default => 'chat/files',
            };
            $data['file_path'] = $request->file('file')->store($folder, 'public');
            $data['file_name'] = $request->file('file')->getClientOriginalName();
        }

        $message = DB::transaction(function () use ($conversation, $data) {
            $message = ChatMessage::create($data);
            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        $message->load('sender');

        try {
            broadcast(new ChatMessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::debug('Message broadcast failed (ignored): ' . $e->getMessage());
        }

        $receiver = $conversation->otherUser($request->user()->id);
        $receiver->notify(new NewChatMessageNotification($message));

        return new ChatMessageResource($message);
    }

    /** POST /api/chat/conversations/{conversation}/typing — Typing Indicator realtime. */
    public function typing(Request $request, ChatConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $validated = $request->validate([
            'is_typing' => ['required', 'boolean'],
        ]);

        try {
            broadcast(new UserTyping($conversation->id, $request->user()->id, $validated['is_typing']))->toOthers();
        } catch (\Exception $e) {
            \Log::debug('Typing broadcast failed (ignored): ' . $e->getMessage());
        }

        return response()->json(['message' => 'OK']);
    }

    public function markRead(Request $request, ChatConversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'OK']);
    }

    /** PATCH /api/chat/messages/{message} — Update message content */
    public function updateMessage(Request $request, ChatMessage $message)
    {
        $userId = $request->user()->id;

        abort_unless($message->sender_id === $userId, 403, 'Tidak dapat mengedit pesan orang lain.');
        abort_unless($message->type === 'text', 422, 'Hanya pesan teks yang dapat diedit.');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message->update([
            'content' => $validated['content'],
        ]);

        $message->load('sender');

        try {
            broadcast(new ChatMessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::debug('Update message broadcast failed (ignored): ' . $e->getMessage());
        }

        return new ChatMessageResource($message);
    }

    /** DELETE /api/chat/messages/{message} — Delete message with scope (me or all) */
    public function deleteMessage(Request $request, ChatMessage $message)
    {
        $userId = $request->user()->id;

        abort_unless($message->sender_id === $userId, 403, 'Tidak dapat menghapus pesan orang lain.');

        $scope = $request->query('scope', 'me');

        abort_unless(in_array($scope, ['me', 'all'], true), 422, 'Scope harus "me" atau "all".');

        if ($scope === 'me') {
            $message->update([
                'is_deleted' => true,
                'deleted_for' => 'me',
                'deleted_by_user_id' => $userId,
                'deleted_at' => now(),
            ]);
        } else {
            $message->update([
                'is_deleted' => true,
                'deleted_for' => 'all',
                'deleted_by_user_id' => $userId,
                'deleted_at' => now(),
                'content' => '[Pesan dihapus]',
                'file_path' => null,
                'file_name' => null,
            ]);
        }

        $message->load('sender');

        try {
            broadcast(new ChatMessageSent($message))->toOthers();
        } catch (\Exception $e) {
            \Log::debug('Delete message broadcast failed (ignored): ' . $e->getMessage());
        }

        return new ChatMessageResource($message);
    }

    protected function authorizeConversation(Request $request, ChatConversation $conversation): void
    {
        $userId = $request->user()->id;

        abort_unless(in_array($userId, [$conversation->user_one_id, $conversation->user_two_id], true), 403);
    }
}