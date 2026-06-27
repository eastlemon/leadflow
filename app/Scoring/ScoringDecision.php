<?php

declare(strict_types=1);

namespace App\Scoring;

/**
 * Result of a single BankScoringService run.
 *
 * Three terminal states:
 *  - PASS      : lead cleared every rule, ready for the bank API call.
 *  - REJECTED  : lead was filtered out (blacklist, whitelist miss, etc).
 *  - DUPLICATE : lead was rejected because we recently processed the same INN.
 *  - DISABLED  : the user turned off pre-flight for this bank.
 *
 * The legacy TellFax `check()` returned loose strings ("ok", "duple",
 * "Alfa: skip INN") that the calling code had to string-match. Here we
 * keep the human-readable reason in `reason` and a stable code in `code`
 * so the UI can render an icon per category.
 */
final class ScoringDecision
{
    public const PASS = 'pass';
    public const REJECTED = 'rejected';
    public const DUPLICATE = 'duplicate';
    public const DISABLED = 'disabled';

    public function __construct(
        public readonly string $status,
        public readonly ?string $code = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function pass(): self
    {
        return new self(self::PASS);
    }

    public static function rejected(string $code, string $reason): self
    {
        return new self(self::REJECTED, $code, $reason);
    }

    public static function duplicate(string $reason): self
    {
        return new self(self::DUPLICATE, 'duplicate', $reason);
    }

    public static function disabled(): self
    {
        return new self(self::DISABLED, 'disabled', 'pre-flight disabled for this bank');
    }

    public function isPass(): bool
    {
        return $this->status === self::PASS;
    }

    /**
     * Any "we stopped here, don't call the bank" verdict.
     */
    public function blocksSend(): bool
    {
        return in_array($this->status, [self::REJECTED, self::DUPLICATE, self::DISABLED], true);
    }
}
