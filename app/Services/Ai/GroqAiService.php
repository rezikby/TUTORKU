<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAiService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = config('services.groq.model');
        $this->apiUrl = config('services.groq.api_url');
    }

    /**
     * Chat dengan AI menggunakan Groq API (free & unlimited)
     * 
     * @param string $message Pesan dari user
     * @param array $conversationHistory History percakapan sebelumnya (optional)
     * @return array Response dengan structure: ['status' => 'success'|'error', 'message' => string, 'data' => [...]]
     */
    public function chat(string $message, array $conversationHistory = []): array
    {
        if (!$this->apiKey) {
            Log::warning('Groq API key not configured');
            return [
                'status' => 'error',
                'message' => 'AI service is not configured. Please set GROQ_API_KEY in environment.',
            ];
        }

        try {
            // Siapkan messages array dengan system prompt + conversation history + user message
            $messages = [
                [
                    'role' => 'system',
                    'content' => 'Anda adalah asisten AI yang membantu pengguna di platform TUTORKU. '
                        . 'Jawab dengan bahasa Indonesia yang jelas, ringkas, dan membantu. '
                        . 'Format respons Anda dengan paragraf yang rapi dan mudah dibaca.'
                ],
            ];

            // Tambahkan conversation history jika ada
            if (!empty($conversationHistory)) {
                $messages = array_merge($messages, $conversationHistory);
            }

            // Tambahkan pesan user terbaru
            $messages[] = [
                'role' => 'user',
                'content' => $message,
            ];

            Log::info('Groq AI Chat Request', [
                'model' => $this->model,
                'message_count' => count($messages),
            ]);

            // Call Groq API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => 2048,
                'temperature' => 0.7,
                'top_p' => 0.9,
            ]);

            if (!$response->successful()) {
                Log::error('Groq API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Gagal mendapatkan respons dari AI. Silakan coba lagi.',
                    'error_details' => $response->json('error.message', 'Unknown error'),
                ];
            }

            $data = $response->json();
            $aiMessage = $data['choices'][0]['message']['content'] ?? '';

            if (!$aiMessage) {
                return [
                    'status' => 'error',
                    'message' => 'Respons AI kosong. Silakan coba pertanyaan lain.',
                ];
            }

            Log::info('Groq AI Chat Success', [
                'message_length' => strlen($aiMessage),
            ]);

            return [
                'status' => 'success',
                'message' => 'Respons dari AI berhasil didapatkan.',
                'data' => [
                    'response' => $this->formatResponse($aiMessage),
                    'timestamp' => now(),
                    'model' => $this->model,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Groq AI Exception', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memproses permintaan. Silakan coba lagi.',
                'error_details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Format respons AI agar lebih rapi
     * - Pisahkan paragraf
     * - Format list/bullet points
     * - Highlight poin penting
     */
    private function formatResponse(string $response): string
    {
        // Hapus trailing/leading whitespace
        $response = trim($response);

        // Pisahkan paragraf dengan newline double
        $response = preg_replace('/\n\n+/', "\n\n", $response);

        // Format numbered list
        $response = preg_replace('/^(\d+)\.\s+/m', '$1. ', $response);

        // Format bullet list
        $response = preg_replace('/^[\-\*]\s+/m', '• ', $response);

        // Replace bold markdown syntax jika ada
        $response = preg_replace('/\*\*(.*?)\*\*/u', '**$1**', $response);

        // Hapus emoji yang tidak perlu (optional, bisa dihapus kalau mau tetap punya emoji)
        // $response = preg_replace('/[^\p{L}\p{N}\s\.\,\!\?\:\-\(\)]/u', '', $response);

        return $response;
    }
}
