# TUTORKU — Backend API (Laravel)

Backend untuk platform bimbingan belajar **TUTORKU** (online & offline), dibuat 100%
mengikuti scope frontend yang sudah ada: 10 halaman yang sudah diimplementasikan
(Landing, Cari Tutor, Detail Tutor, Booking, Live Class, Chat, Dashboard Siswa,
Dashboard Tutor, Forum, Progress) **plus** 7 modul tambahan dari dokumen master
prompt (Admin Dashboard, Payment Gateway, Notification Center, Settings, Tutor
Registration, About, Contact) — total 17 modul.

Dibangun dengan **Laravel 13**, **Laravel Sanctum** (auth berbasis token untuk
SPA React), dan **Laravel Reverb** (WebSocket server resmi Laravel, open-source
& gratis) untuk semua fitur realtime: chat, notifikasi, dan signaling WebRTC
Live Class.

---

## 1. Stack & Keputusan Teknis

| Kebutuhan | Pilihan | Alasan |
|---|---|---|
| Auth | Laravel Sanctum (token) | Ringan, cocok untuk SPA React terpisah domain |
| Realtime (chat, notif, live class) | **Laravel Reverb** | Resmi dari Laravel, self-hosted, **gratis** tanpa biaya per-koneksi (beda dengan Pusher) |
| Video call Live Class | **WebRTC peer-to-peer** + Reverb sebagai signaling | Gratis, tanpa biaya per-menit seperti Agora/Daily.co. STUN server gratis dari Google. Backend hanya menyampaikan offer/answer/ICE-candidate, video tidak pernah lewat server |
| Payment Gateway | Midtrans **atau** Xendit (pilih lewat `.env`) | Dua-duanya populer di Indonesia, terintegrasi lewat REST API langsung (tanpa SDK tambahan) |
| Notifikasi WhatsApp | Fonnte (opsional) | Ada free trial, gampang dipakai untuk MVP |
| Database default | SQLite | Tidak perlu setup server DB untuk development. Bisa ganti ke MySQL/PostgreSQL kapan saja lewat `.env` |

---

## 2. Instalasi

```bash
# 1. Masuk ke folder project
cd TUTORKU-backend

# 2. Install dependency PHP
composer install

# 3. Siapkan .env (sudah ada file .env, tinggal generate key)
php artisan key:generate

# 4. Siapkan database (default: SQLite)
touch database/database.sqlite

# 5. Jalankan migrasi + seed data demo
#    (data demo persis meniru data dummy di frontend: 6 tutor, 3 thread forum, dst)
php artisan migrate --seed

# 6. Buat symlink storage (supaya foto profil, sertifikat, dll bisa diakses publik)
php artisan storage:link

# 7. Jalankan server
php artisan serve
```

## 2.1 Konfigurasi Email OTP (Resend gratis)

Untuk mengirim OTP ke mailbox nyata, gunakan provider gratis Resend:

- Daftar gratis di https://resend.com
- Isi `RESEND_API_KEY` di `.env`
- Set `MAIL_MAILER=resend`
- Jika belum memverifikasi domain pengirim, gunakan sandbox alamat `MAIL_FROM_ADDRESS="onboarding@resend.dev"`
- Jika sudah memverifikasi domain sendiri, set `MAIL_FROM_ADDRESS` ke email domain tersebut

Jika masih dalam development lokal dan belum siap kirim ke inbox nyata, Anda masih boleh menggunakan `MAIL_MAILER=smtp` dengan Mailpit.

API akan berjalan di `http://localhost:8000/api`.

### Menjalankan realtime (Reverb) + queue worker

Notifikasi (email/WhatsApp) dan broadcast event dikirim lewat queue supaya
request HTTP tetap cepat. Jalankan di terminal terpisah:

```bash
# WebSocket server untuk chat, notifikasi, & live class signaling
php artisan reverb:start

# Queue worker untuk memproses notifikasi & job lain
php artisan queue:work
```

Atau jalankan ketiganya sekaligus untuk development:

```bash
composer require laravel/sail --dev   # opsional, atau jalankan manual 3 terminal:
php artisan serve & php artisan reverb:start & php artisan queue:work
```

### Koneksi dari Frontend (React + Vite)

Tambahkan di `.env` frontend:

```
VITE_API_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=TUTORKU-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Install `laravel-echo` + `pusher-js` di frontend (Reverb pakai protokol yang
kompatibel dengan Pusher, jadi library client-nya sama):

```bash
npm install --save laravel-echo pusher-js
```

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: `${import.meta.env.VITE_API_URL}/../broadcasting/auth`,
  auth: { headers: { Authorization: `Bearer ${token}` } },
});

// Contoh: dengar pesan chat baru
window.Echo.private(`chat.${conversationId}`).listen('.message.sent', (e) => { ... });

// Contoh: dengar sinyal WebRTC di Live Class
window.Echo.join(`live-session.${roomId}`)
  .listen('.webrtc.signal', (e) => { ... })
  .listen('.whiteboard.updated', (e) => { ... });
```

---

## 3. Akun Demo

Akun demo dibuat saat menjalankan seeder proyek. Saat ini, akun yang tersedia antara lain:

| Role | Email | Catatan |
|---|---|---|
| Admin | `rezikobay75@gmail.com` | Akun admin default; password diatur di file seeder `database/seeders/AdminSeeder.php` |
| Tutor | `rezicopilot1@gmail.com` | Matematika & Fisika — Verified |
| Tutor | `rezicopilot2@gmail.com` | Bahasa Inggris — Verified |
| Tutor | `rezicopilot3@gmail.com` | Kimia & Biologi — Verified |
| Tutor | `rezicopilot4@gmail.com` | Matematika — Verified |
| Tutor | `rezicopilot5@gmail.com` | Fisika & Matematika — Verified |
| Tutor | `rezicopilot6@gmail.com` | Bahasa Indonesia — Verified |
| Tutor | `rezicopilot7@gmail.com` | Matematika & Bahasa Inggris — Verified |
| Tutor | `rezicopilot8@gmail.com` | Biologi & Kimia — Verified |
| Tutor | `rezicopilot9@gmail.com` | Fisika — Verified |
| Tutor | `rezicopilot10@gmail.com` | Bahasa Inggris & Bahasa Indonesia — Verified |
| Tutor | `rezicopilot11@gmail.com` | Matematika & UTBK — Verified |

Login: `POST /api/auth/login` dengan `email` & `password`, response berisi
`token` yang dipakai sebagai `Authorization: Bearer {token}` di setiap
request berikutnya.

---

## 4. Payment Gateway — Mode Sandbox/Testing

Tanpa mengisi `MIDTRANS_SERVER_KEY` / `XENDIT_SECRET_KEY` di `.env`, endpoint
booking tetap berjalan tapi `payment_url` akan kosong (gateway gagal membuat
transaksi karena key kosong). Untuk testing tanpa akun gateway sungguhan,
pakai endpoint simulasi (hanya aktif saat `APP_ENV=local`):

```
POST /api/payments/{payment}/simulate
Body: { "status": "paid" }   // atau "failed" / "expired"
```

Begitu daftar akun sandbox di [Midtrans](https://dashboard.midtrans.com) atau
[Xendit](https://dashboard.xendit.co), isi key di `.env` dan booking akan
otomatis membuat transaksi sungguhan + redirect ke halaman pembayaran asli.

Daftarkan juga URL webhook di dashboard masing-masing:
- Midtrans: `https://domain-kamu.com/api/payments/webhook/midtrans`
- Xendit: `https://domain-kamu.com/api/payments/webhook/xendit`

---

## 5. Struktur Modul & Endpoint

### 1) Landing Page
```
GET  /api/platform/stats        — total tutor, siswa, kota, kepuasan
```

### 2) Cari Tutor
```
GET  /api/subjects
GET  /api/tutors?q=&subject_id=&level=&price_min=&price_max=&min_rating=&city=&mode=&sort=&page=
```

### 3) Detail Tutor
```
GET  /api/tutors/{id}
GET  /api/tutors/{id}/reviews
```

### 4) Booking
```
GET    /api/bookings
POST   /api/bookings                       — buat booking + auto-inisiasi pembayaran
GET    /api/bookings/{id}
POST   /api/bookings/{id}/confirm          — tutor konfirmasi (otomatis juga saat payment paid)
POST   /api/bookings/{id}/cancel
POST   /api/bookings/{id}/complete
POST   /api/bookings/{id}/review           — siswa beri ulasan setelah selesai
```

### 5) Live Class (WebRTC + Whiteboard, realtime via Reverb)
```
GET    /api/bookings/{id}/live-session
POST   /api/bookings/{id}/live-session/join
POST   /api/bookings/{id}/live-session/end
POST   /api/bookings/{id}/live-session/signal      — offer / answer / ice-candidate / hangup
POST   /api/bookings/{id}/live-session/whiteboard   — sinkronisasi gambar whiteboard
POST   /api/bookings/{id}/session-note              — rekap & catatan sesi (tutor)
GET    /api/bookings/{id}/session-note
```

### 6) Chat (realtime via Reverb)
```
GET    /api/chat/conversations
POST   /api/chat/conversations/start        — { user_id, booking_id? }
GET    /api/chat/conversations/{id}/messages
POST   /api/chat/conversations/{id}/messages — type: text|image|file|voice
POST   /api/chat/conversations/{id}/read
```

### 7) Dashboard Siswa
```
GET  /api/dashboard/siswa
```

### 8) Dashboard Tutor
```
GET  /api/dashboard/tutor
```

### 9) Admin Dashboard
```
GET   /api/admin/dashboard
GET   /api/admin/tutors?status=pending|verified|rejected|all
GET   /api/admin/tutors/{id}
POST  /api/admin/tutors/{id}/approve
POST  /api/admin/tutors/{id}/reject          — { note }
GET   /api/admin/users?role=&status=&q=
PUT   /api/admin/users/{id}/status           — { status: active|suspended|pending }
GET   /api/admin/reports
PUT   /api/admin/reports/{id}/resolve        — { status, note }
```

### 10) Forum Komunitas
```
GET   /api/forum/categories
GET   /api/forum/posts?category_id=&q=&sort=trending|terbaru|belum_terjawab
POST  /api/forum/posts
GET   /api/forum/posts/{id}
POST  /api/forum/posts/{id}/like
POST  /api/forum/posts/{id}/solved
POST  /api/forum/posts/{id}/comments
POST  /api/forum/comments/{id}/like
POST  /api/forum/comments/{id}/mark-solution
DELETE /api/forum/comments/{id}
POST  /api/reports                          — lapor post/komentar/user yang melanggar
```

### 11) Progress Tracker
```
GET  /api/progress     — kehadiran, nilai rata-rata, jam belajar per bulan,
                          progress per mata pelajaran, achievement/badge
```

### 12) Payment
```
GET   /api/payments/{id}
POST  /api/payments/{id}/simulate            — khusus local/testing
POST  /api/payments/webhook/midtrans         — dipanggil server Midtrans
POST  /api/payments/webhook/xendit           — dipanggil server Xendit
```

### 13) Notification Center
```
GET   /api/notifications?unread_only=
GET   /api/notifications/unread-count
POST  /api/notifications/{id}/read
POST  /api/notifications/read-all
```

### 14) Settings
```
GET  /api/profile
PUT  /api/profile              — name, phone, gender, date_of_birth, address, city, avatar (file)
PUT  /api/profile/password     — current_password, password, password_confirmation
GET  /api/settings             — language, dark_mode, notif_email, notif_whatsapp, notif_push
PUT  /api/settings
```

### 15) Tutor Registration (stepper)
```
GET   /api/tutor/registration
PUT   /api/tutor/registration/step-2     — data diri, mapel, harga, jenjang
PUT   /api/tutor/registration/step-3     — pendidikan & pengalaman
POST  /api/tutor/registration/step-4     — sertifikat, CV, identitas (file upload)
POST  /api/tutor/registration/submit     — ajukan verifikasi ke admin

GET/POST/PUT/DELETE /api/tutor/availabilities  — kelola jadwal ketersediaan
GET/POST/DELETE      /api/tutor/materials      — kelola materi belajar
```

### 16) About
```
GET  /api/about     — misi, visi, tim, FAQ
```

### 17) Contact
```
POST /api/contact   — { name, email, subject?, message }
```

---

## 6. Catatan Implementasi Penting

- **Role & otorisasi**: setiap user punya `role` (`siswa` / `tutor` / `admin`).
  Middleware `role:tutor`, `role:siswa`, `role:admin` membatasi akses route
  grup masing-masing (lihat `routes/api.php`).
- **File upload**: avatar, sertifikat, CV, materi, dan lampiran chat disimpan
  di `storage/app/public/...` dan diakses lewat `/storage/...` setelah
  `php artisan storage:link`. Untuk production, ganti `FILESYSTEM_DISK` ke
  `s3` di `.env` + isi kredensial AWS.
- **Notifikasi multi-channel**: setiap notifikasi penting (status booking,
  pembayaran, chat baru, balasan forum, verifikasi tutor) otomatis terkirim
  ke 3 channel sekaligus: **database** (muncul di Notification Center),
  **broadcast** (realtime lewat Reverb), dan **WhatsApp/email** untuk hal-hal
  penting seperti konfirmasi booking & pembayaran.
- **Background job**: notifikasi pakai `ShouldQueue`, jadi **wajib** jalankan
  `php artisan queue:work` supaya notifikasi benar-benar terkirim.
- **Reminder sesi**: untuk pengingat "sesi akan dimulai", jadwalkan scheduled
  command di `routes/console.php` yang query booking H-1 jam lalu kirim
  `SessionReminderNotification` — silakan tambahkan sesuai kebutuhan operasional
  kamu (tidak diaktifkan otomatis di project ini supaya tidak mengirim
  notifikasi saat development).
- **Konsistensi dengan frontend**: data seed (`DemoDataSeeder`) meniru persis
  data dummy yang dipakai di `App.tsx` (6 tutor, 3 thread forum, grafik jam
  belajar & pendapatan) supaya begitu frontend disambungkan ke API ini,
  tampilannya langsung mirip versi mockup.

---

## 7. Testing Cepat (curl)

```bash
# Login sebagai siswa demo
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"siswa@TUTORKU.id","password":"password"}'

# Pakai token dari response di atas
curl http://localhost:8000/api/tutors \
  -H "Authorization: Bearer {token}"

curl http://localhost:8000/api/dashboard/siswa \
  -H "Authorization: Bearer {token}"
```

---

## 8. Yang Belum/Bisa Dikembangkan Lebih Lanjut

- Validasi nomor rekening tutor saat pencairan dana (payout) — saat ini baru
  tersimpan sebagai data, proses pencairan manual oleh admin.
- Rate limiting khusus per role (saat ini pakai default Laravel throttle).
- Export laporan admin ke Excel/PDF.
- Push notification native (FCM) — struktur notifikasi sudah generic,
  tinggal tambah channel baru mengikuti pola `WhatsAppChannel`.
# TUTORKU_BACKEND
# TUTORKU_BACKEND
# TUTORKU_BACKEND
# TUTORKU_LARAVEL
