<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\LiveSessionController;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class LiveSessionPauseDurationTest extends TestCase
{
    public function test_it_clamps_negative_pause_duration_to_zero(): void
    {
        $controller = new class extends LiveSessionController {
            public function exposeCalculatePausedSeconds(Carbon $now, Carbon $pausedAt): int
            {
                return $this->calculatePausedSeconds($now, $pausedAt);
            }
        };

        $now = Carbon::parse('2026-07-04 17:08:04');
        $pausedAt = Carbon::parse('2026-07-04 17:09:46');

        $this->assertSame(0, $controller->exposeCalculatePausedSeconds($now, $pausedAt));
    }
}
