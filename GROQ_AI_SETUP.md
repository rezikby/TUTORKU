# Groq AI Chat Integration Setup

## ✅ Backend Setup - SELESAI

Backend sudah siap dengan:
- ✅ Service: `app/Services/Ai/GroqAiService.php` - Handle Groq API calls
- ✅ Controller: `app/Http/Controllers/Api/AiChatController.php` - API endpoints
- ✅ Routes: `/api/ai/chat` dan `/api/ai/chat-in-conversation/{id}`
- ✅ Config: `config/services.php` + `.env` dengan GROQ_API_KEY

## 📋 Langkah Selanjutnya

### 1. **Dapatkan Groq API Key (GRATIS)**
   - Buka: https://console.groq.com/keys
   - Sign up dengan akun Google/GitHub
   - Generate API key baru
   - Copy API key ke `.env` file:
     ```
     GROQ_API_KEY=gsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
     GROQ_MODEL=mixtral-8x7b-32768
     ```

### 2. **Test Endpoint dari Postman/Frontend**

```bash
POST /api/ai/chat
Authorization: Bearer {token}
Content-Type: application/json

{
  "message": "Apa itu machine learning?",
  "conversation_history": []
}
```

**Response Format:**
```json
{
  "status": "success",
  "message": "Respons dari AI berhasil didapatkan.",
  "data": {
    "response": "Machine learning adalah...\n\nCara kerjanya:\n• Input data\n• Proses training\n• Output prediction",
    "timestamp": "2026-06-30T12:00:00Z",
    "model": "mixtral-8x7b-32768"
  }
}
```

### 3. **Update Frontend (Optional)**

Ganti endpoint OpenCounter.ai dengan:
```javascript
// Dari OpenCounter.ai ke Groq API
const response = await fetch('https://rezi-laravel.nlabs.id/api/ai/chat', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    message: userMessage,
    conversation_history: chatHistory
  })
});

const data = await response.json();
if (data.status === 'success') {
  displayAiResponse(data.data.response); // Response sudah terformat rapi
}
```

## 🎯 Fitur

- **Free & Unlimited** - Groq tidak membatasi request
- **Fast** - Response time sangat cepat (< 1 detik)
- **Formatted Response** - AI response otomatis di-format dengan:
  - Paragraf terpisah yang rapi
  - Bullet points & numbered lists
  - Bold text support
  - Emoji support
- **Conversation History** - Support multi-turn conversation
- **Error Handling** - Proper error messages & logging

## 🔒 Security

- API Key di-store di `.env` (tidak di-hardcode)
- Endpoint memerlukan auth token (Sanctum)
- Rate limiting bisa ditambahkan di controller jika perlu
- Logging semua request untuk monitoring

## 📝 Notes

- Model: `mixtral-8x7b-32768` adalah model terbaik dari Groq (bisa diganti)
- Max tokens: 2048 (bisa dikurangi untuk response lebih singkat)
- Temperature: 0.7 (balanced antara creative & consistent)
- Timeout: 30 detik

## 🆘 Troubleshooting

**Error: "AI service is not configured"**
- Pastikan `GROQ_API_KEY` sudah diisi di `.env`
- Jalankan `php artisan config:clear`

**Error: 402 / "Invalid API Key"**
- Cek API key di https://console.groq.com/keys
- Pastikan key tidak expired/invalid
- Generate key baru jika perlu

**Slow Response**
- Groq biasanya sangat cepat
- Cek internet connection
- Cek Groq service status
