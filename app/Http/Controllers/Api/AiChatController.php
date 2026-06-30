<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\GroqAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AiChatController extends Controller
{
    public function __construct(protected GroqAiService $aiService)
    {
    }

    /**
     * POST /api/ai/chat
     * Chat dengan AI Groq (free & unlimited)
     * 
     * Request body:
     * {
     *   "message": "Pertanyaan saya?",
     *   "conversation_history": [
     *     {"role": "user", "content": "Pertanyaan sebelumnya"},
     *     {"role": "assistant", "content": "Jawaban sebelumnya"}
     *   ]
     * }
     */
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'conversation_history' => ['nullable', 'array', 'max:20'],
            'conversation_history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'conversation_history.*.content' => ['required', 'string', 'max:5000'],
        ]);

        Log::info('AI Chat Request', [
            'user_id' => $request->user()?->id,
            'message_length' => strlen($validated['message']),
            'history_count' => count($validated['conversation_history'] ?? []),
        ]);

        $result = $this->aiService->chat(
            $validated['message'],
            $validated['conversation_history'] ?? []
        );

        if ($result['status'] === 'error') {
            return response()->json($result, 400);
        }

        return response()->json($result, 200);
    }

    /**
     * POST /api/ai/chat-in-conversation/{conversationId}
     * Chat dengan AI dalam konteks percakapan tertentu
     * Berguna untuk mendapatkan saran/bantuan terkait percakapan
     */
    public function chatInConversation(Request $request, $conversationId)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Ambil conversation history dari chat messages sebelumnya
        $conversationHistory = [];
        // TODO: Implementasi ambil message history dari database jika diperlukan

        Log::info('AI Chat in Conversation', [
            'user_id' => $request->user()?->id,
            'conversation_id' => $conversationId,
        ]);

        $result = $this->aiService->chat(
            $validated['message'],
            $conversationHistory
        );

        if ($result['status'] === 'error') {
            return response()->json($result, 400);
        }

        return response()->json($result, 200);
    }
}
