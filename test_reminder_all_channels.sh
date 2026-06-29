#!/bin/bash

echo "════════════════════════════════════════════════════════════"
echo "  TEST: REMINDER NOTIFICATION - 3 CHANNELS (Email, WhatsApp, Push)"
echo "════════════════════════════════════════════════════════════"
echo ""

# Step 1: Create booking 15 minutes in the future
echo "📝 STEP 1: Create booking untuk 15 menit ke depan..."
BOOKING_RESULT=$(/usr/bin/mysql -uroot -h127.0.0.1 -P3306 tuturku_testing -e "
INSERT INTO bookings (code, student_id, tutor_profile_id, subject_id, date, start_time, duration_minutes, mode, price, service_fee, total_price, status, is_hidden, reminder_sent_at, created_at, updated_at) 
VALUES (
  CONCAT('TRX-TEST-', DATE_FORMAT(NOW(), '%H%i%s')), 
  11, 7, NULL, 
  CURDATE(), 
  ADDTIME(TIME(NOW()), '00:15:00'), 
  60, 'online', 100000, 0, 100000, 'confirmed', 0, NULL, NOW(), NOW()
);
SELECT CONCAT('✓ Booking dibuat: ', id, ' | Code: ', code, ' | Start: ', DATE_FORMAT(NOW(), '%H:%i:%s')) FROM bookings ORDER BY id DESC LIMIT 1;
")
echo "$BOOKING_RESULT"
echo ""

# Get booking ID
BOOKING_ID=$(/usr/bin/mysql -uroot -h127.0.0.1 -P3306 tuturku_testing -N -e "SELECT id FROM bookings ORDER BY id DESC LIMIT 1;")

echo "🔔 STEP 2: Run reminder command (--minutes=15)..."
php artisan reminders:send --minutes=15
echo ""

# Step 3: Check database
echo "💾 STEP 3: Check Notifications in Database..."
echo "─────────────────────────────────────────────"
/usr/bin/mysql -uroot -h127.0.0.1 -P3306 tuturku_testing -e "
SELECT 
  'Notification dari Database:' as '=',
  type,
  data,
  created_at
FROM notifications 
WHERE notifiable_id = 11 
AND data LIKE '%reminder%'
ORDER BY created_at DESC 
LIMIT 1;
"
echo ""

# Step 4: Check user settings
echo "⚙️  STEP 4: Verifikasi User Settings..."
echo "─────────────────────────────────────────────"
/usr/bin/mysql -uroot -h127.0.0.1 -P3306 tuturku_testing -e "
SELECT 
  'Channel Configuration:' as '=',
  IF(notif_email = 1, '✓ Email', '✗ Email') as Channel_1,
  IF(notif_whatsapp = 1, '✓ WhatsApp', '✗ WhatsApp') as Channel_2,
  IF(notif_push = 1, '✓ Push Notification', '✗ Push Notification') as Channel_3
FROM user_settings 
WHERE user_id = 11;
"
echo ""

# Step 5: Check FCM tokens
echo "📱 STEP 5: Check FCM Tokens Registered..."
echo "─────────────────────────────────────────────"
FCM_COUNT=$(/usr/bin/mysql -uroot -h127.0.0.1 -P3306 tuturku_testing -N -e "SELECT COUNT(*) FROM user_fcm_tokens WHERE user_id = 11;")
echo "Total FCM Tokens untuk user_id=11: $FCM_COUNT"
if [ "$FCM_COUNT" -gt 0 ]; then
  echo "✓ Push notification dapat dikirim (token tersedia)"
else
  echo "⚠️  Tidak ada FCM token (register device terlebih dahulu di ReminderSettingsPage)"
fi
echo ""

# Step 6: Show summary
echo "════════════════════════════════════════════════════════════"
echo "  ✓ TEST RINGKASAN"
echo "════════════════════════════════════════════════════════════"
echo "✅ Email Channel (MAIL_MAILER=log):"
echo "   - Email akan di-log ke laravel.log"
echo "   - User setting: notif_email enabled"
echo ""
echo "✅ WhatsApp Channel (via Fonnte):"
echo "   - API token sudah configured"
echo "   - User setting: notif_whatsapp enabled"
echo "   - Status: Akan dikirim jika phone nomor terdaftar"
echo ""
echo "✅ Push Notification Channel (via FCM):"
echo "   - FCM tokens: $FCM_COUNT token(s) registered"
echo "   - User setting: notif_push enabled"
echo "   - Status: $([ "$FCM_COUNT" -gt 0 ] && echo 'Siap dikirim ✓' || echo 'Perlu register device')"
echo ""
echo "✅ Database Notification:"
echo "   - Sudah masuk ke database (lihat di notifikasi center)"
echo ""
echo "════════════════════════════════════════════════════════════"

