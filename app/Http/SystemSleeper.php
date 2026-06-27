<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

/**
 * Production sleeper — uses `sleep()` between attempts.
 *
 * Wrapped in a try/catch so a signal-driven interruption (e.g.
 * pcntl alarm, queue worker shutdown) doesn't surface as a fatal
 * during a retry loop; we re-throw as a runtime exception so the
 * caller can decide whether to bail out or continue.
 */
final class SystemSleeper implements Sleeper
{
    public function sleep(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        try {
            sleep($seconds);
        } catch (\Throwable $e) {
            throw new RuntimeException('Retry sleep interrupted', previous: $e);
        }
    }
}
