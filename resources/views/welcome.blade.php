<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TUTORKU Backend</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main>
        <h1>TUTORKU Backend is Ready</h1>
        <p>API Laravel untuk platform bimbingan belajar TUTORKU sudah siap digunakan.</p>
        <p>Gunakan endpoint API di <code>/api</code> dan jalankan fitur realtime melalui Reverb jika diperlukan.</p>
        <p id="status">Memuat status aplikasi...</p>
    </main>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
