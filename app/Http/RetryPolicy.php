<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Per-call retry policy. Built from an AdapterConfig in production,
 * constructed directly in tests.
 *
 * Retryable:
 *  - 5xx (server side, may recover)
 *  - 429 (rate limited — should also honour Retry-After header,
 *    which the caller can do by passing a custom policy)
 *  - ConnectionException (status === null)
 *
 * Not retryable:
 *  - 4xx other than 429 (client side; retrying won't help)
 *  - attempts exhausted
 */
final class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts,
        public readonly int $baseBackoffSeconds,
        public readonly int $jitterSeconds = 0,
    ) {
    }

    public static function noRetry(): self
    {
        return new self(maxAttempts: 1, baseBackoffSeconds: 0);
    }

    public static function fromConfig(\App\Adapters\AdapterConfig $config): self
    {
        $jitter = (int) ceil($config->retryBackoffSeconds / 2);

        return new self(
            maxAttempts: max(1, $config->retryAttempts),
            baseBackoffSeconds: max(0, $config->retryBackoffSeconds),
            jitterSeconds: $jitter,
        );
    }

    /**
     * $status === null represents a transport-level failure
     * (ConnectionException in the Laravel HTTP client).
     */
    public function isRetryable(?int $status, int $attempt): bool
    {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        if ($status === null) {
            return true;
        }

        if ($status >= 500) {
            return true;
        }

        return $status === 429;
    }

    /**
     * Exponential backoff with optional jitter.
     * attempt 1 (after the first failed try) -> base * 1
     * attempt 2 (after the second failed try) -> base * 2
     * ...
     */
    public function backoffSeconds(int $attempt): int
    {
        $exp = max(0, $attempt - 1);
        $delay = $this->baseBackoffSeconds * (1 << $exp);

        if ($this->jitterSeconds > 0) {
            $delay += random_int(0, $this->jitterSeconds);
        }

        return $delay;
    }
}
