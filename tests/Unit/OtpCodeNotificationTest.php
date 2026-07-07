<?php

namespace Tests\Unit;

use App\Notifications\OtpCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class OtpCodeNotificationTest extends TestCase
{
    public function test_otp_notification_is_not_queueable(): void
    {
        $reflection = new \ReflectionClass(OtpCodeNotification::class);

        $this->assertNotContains(ShouldQueue::class, $reflection->getInterfaceNames());
    }
}
