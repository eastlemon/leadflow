<?php

declare(strict_types=1);

namespace App\Http\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by BankHttpClient when all retry attempts for one bank
 * HTTP request have been exhausted. Listeners can forward to
 * Slack/email/Sentry/etc.
 *
 * Carries everything needed to debug:
 *  - which bank + endpoint
 *  - how many attempts were used
 *  - last status (null = transport error)
 *  - last error / reason
 *  - how long we spent
 */
final class BankRequestFailed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload  the request body, useful for debugging
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly string $systemName,
        public readonly int $attempts,
        public readonly ?int $lastStatus,
        public readonly ?string $lastError,
        public readonly float $elapsedSeconds,
        public readonly array $payload = [],
    ) {
    }
}
