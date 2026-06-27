<?php

declare(strict_types=1);

namespace App\Scoring;

use App\Scoring\Rules\DuplicatePeriodRule;
use App\Scoring\Rules\InnBlacklistRule;
use App\Scoring\Rules\InnWhitelistRule;
use App\Scoring\Rules\OkvedBlacklistRule;
use App\Scoring\Rules\SkipExistingRule;
use App\Scoring\Contracts\ScoringRule;
use App\Services\KeyDetector;
use InvalidArgumentException;

/**
 * Builds the per-bank pre-flight pipeline: the typed `ScoringConfig`
 * parsed from the user's `tune` JSON, plus the list of rules the
 * service will run for that bank.
 *
 * Different banks use different rule sets — ПСБ cares about whitelist
 * + cooldown, Альфа only blacklist + dedup, ВТБ blacklist + cooldown,
 * Урал whitelist + cooldown. The legacy TellFax code had this split
 * across per-bank `models/scoring/X.php` classes. We keep the same
 * behaviour but make it data-driven.
 */
final class ScoringConfigFactory
{
    public function __construct(
        private readonly KeyDetector $keys,
    ) {
    }

    /**
     * @return array{config: ScoringConfig, rules: list<ScoringRule>}
     */
    public function forBank(string $systemName, array $tune): array
    {
        $config = ScoringConfig::fromTune($tune);
        $rules = $this->rulesFor($systemName);

        return ['config' => $config, 'rules' => $rules];
    }

    /**
     * @return list<ScoringRule>
     */
    private function rulesFor(string $systemName): array
    {
        return match ($systemName) {
            'alfa' => [
                new InnBlacklistRule($this->keys),
                new OkvedBlacklistRule($this->keys),
                new SkipExistingRule(),
            ],
            'psb' => [
                new InnBlacklistRule($this->keys),
                new InnWhitelistRule($this->keys),
                new SkipExistingRule(),
                new DuplicatePeriodRule(),
            ],
            'vtb' => [
                new InnBlacklistRule($this->keys),
                new SkipExistingRule(),
                new DuplicatePeriodRule(),
            ],
            'ural' => [
                new InnWhitelistRule($this->keys),
                new SkipExistingRule(),
                new DuplicatePeriodRule(),
            ],
            default => throw new InvalidArgumentException("Unknown bank system_name: {$systemName}"),
        };
    }
}
