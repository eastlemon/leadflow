<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * Structured result of a score() call.
 *
 * `success` indicates the bank accepted the scoring request.
 * `approved` reflects the bank's prequalification verdict.
 * `externalId` is the bank-side id we will need for send()/checkStatus().
 */
class ScoreResult extends Data
{
    public function __construct(
        public bool $success,
        public bool $approved = false,
        public ?string $externalId = null,
        public ?float $score = null,
        public ?string $reason = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {
    }

    public static function ok(string $externalId, ?float $score = null): self
    {
        return new self(
            success: true,
            approved: true,
            externalId: $externalId,
            score: $score,
        );
    }

    public static function rejected(string $reason): self
    {
        return new self(
            success: true,
            approved: false,
            reason: $reason,
        );
    }

    public static function failed(string $reason): self
    {
        return new self(
            success: false,
            reason: $reason,
        );
    }
}
