<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Client\Response;

/**
 * Structured result of a BankHttpClient::withRetry() call.
 *
 * Three shapes, all wrapped here so adapters can pattern-match on
 * a single type instead of juggling Response | null | string.
 *
 *  - ok:             2xx, body is the decoded JSON (or empty array).
 *  - httpError:      non-2xx, body is what the bank sent back.
 *  - transportError: all attempts died on ConnectionException.
 */
final class BankHttpResponse
{
    public function __construct(
        public readonly bool $successful,
        public readonly int $status,
        public readonly ?array $body,
        public readonly int $attempts,
        public readonly float $elapsedSeconds,
        public readonly ?string $error = null,
    ) {
    }

    public static function ok(Response $r, int $attempts, float $elapsed): self
    {
        return new self(
            successful: true,
            status: $r->status(),
            body: self::decode($r),
            attempts: $attempts,
            elapsedSeconds: $elapsed,
        );
    }

    public static function httpError(Response $r, int $attempts, float $elapsed): self
    {
        return new self(
            successful: false,
            status: $r->status(),
            body: self::decode($r),
            attempts: $attempts,
            elapsedSeconds: $elapsed,
            error: $r->reason(),
        );
    }

    public static function transportError(string $message, int $attempts, float $elapsed): self
    {
        return new self(
            successful: false,
            status: 0,
            body: null,
            attempts: $attempts,
            elapsedSeconds: $elapsed,
            error: $message,
        );
    }

    public function isTransportError(): bool
    {
        return $this->status === 0 && $this->error !== null;
    }

    /**
     * Human-readable failure label for log/job rows.
     *
     *   HTTP errors     -> "Alfa score HTTP 502: Bad Gateway"
     *   Transport errors -> "Alfa score: Connection refused"
     */
    public function failureLabel(string $prefix): string
    {
        if ($this->isTransportError()) {
            return "{$prefix}: {$this->error}";
        }

        return "{$prefix} HTTP {$this->status}: {$this->error}";
    }

    /**
     * Convenience accessor mirroring Laravel's Response::json().
     */
    public function json(?string $key = null): mixed
    {
        if ($this->body === null) {
            return null;
        }

        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? null;
    }

    private static function decode(Response $r): ?array
    {
        $body = $r->json();

        return is_array($body) ? $body : null;
    }
}
