# OTP Email & Dev Testing

This document explains why OTP email may not reach external addresses and how to test/send OTPs locally.

## Problem (Resend)
If you use `MAIL_MAILER=resend` and your Resend account hasn't verified a sending domain, Resend will only allow sending "testing" emails to your own account(s). The API error in logs looks like:

```
Request to Resend API failed. Reason: You can only send testing emails to your own email address (your@email.com).
```

To fix for production: verify your domain on Resend (https://resend.com/domains) and set `MAIL_FROM_ADDRESS` to an email on that domain.

## Quick dev/test options

### Option A — Use log driver (fast)
1. In `.env` set:

```
MAIL_MAILER=log
```

2. Clear config and trigger OTP send.

```bash
php artisan config:clear
# trigger registration or send OTP via UI or curl
# then inspect the log for OTP
tail -n 200 storage/logs/laravel.log | grep -i 'OTP\|Send email OTP\|OTP DEV-MODE'
```

You will see the OTP printed in the log.

### Option B — Use Mailpit (recommended for dev)
1. Run Mailpit (Docker):

```bash
docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit
```

2. In `.env` set:

```
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=onboarding@local.test
MAIL_FROM_NAME="TUTORKU Dev"
```

3. Clear config and send OTP. Mailpit UI will be available at `http://localhost:8025`.

```bash
php artisan config:clear
# trigger OTP
```

### Option C — Configure Resend for production
1. Add and verify domain at: https://resend.com/domains
2. Copy DNS TXT records to your DNS provider and wait for verification.
3. Set `.env`:

```
MAIL_MAILER=resend
RESEND_API_KEY=key_xxx
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
MAIL_FROM_NAME="TUTORKU"
```

4. Clear config and test sending.

```bash
php artisan config:clear
# trigger OTP
```

## Test curl examples

Register (request OTP):

```bash
curl -X POST 'http://localhost:8000/api/auth/register' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"081234567890","name":"Tester"}'
```

Send phone OTP directly:

```bash
curl -X POST 'http://localhost:8000/api/auth/phone/send-otp' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"081234567890"}'
```

Verify OTP:

```bash
curl -X POST 'http://localhost:8000/api/auth/phone/verify-otp' \
  -H 'Content-Type: application/json' \
  -d '{"phone":"081234567890","code":"12345"}'
```

## Helpful log checks

- Check for Resend errors:

```bash
tail -n 200 storage/logs/laravel.log | grep -i 'Resend\|Send email OTP\|OTP DEV-MODE' -n
```

- Look for Whatsapp OTP (dev-mode prints):

```bash
grep -n "\[OTP DEV-MODE\]" storage/logs/laravel.log | tail -n 20
```


If you want, I can:
- prepare a `.env.dev` file with Mailpit settings,
- or guide you step-by-step to verify a domain in Resend and test production sending.
