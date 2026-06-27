<?php

declare(strict_types=1);

namespace App\Scoring\Rules;

use App\Data\LeadData;
use App\Scoring\Contracts\ScoringRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;
use App\Services\KeyDetector;

/**
 * Restrict the bank to a strict whitelist of ИНН prefixes.
 *
 * TellFax `models/scoring/Psb::check()` / `models/scoring/Ural::check()`
 * use `inn_only` as an explicit allow-list. Empty whitelist means the
 * rule is inactive (and the bank accepts every ИНН) — the inverse of the
 * blacklist, where empty means "nothing blacklisted".
 */
final class InnWhitelistRule implements ScoringRule
{
    public function __construct(
        private readonly KeyDetector $keys,
    ) {
    }

    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if ($tune->innWhitelist === []) {
            return ScoringDecision::pass();
        }

        if (! $this->keys->isAllowedByWhiteList($lead->inn, $tune->innWhitelist)) {
            return ScoringDecision::rejected('inn_whitelist', "ИНН {$lead->inn} не в белом списке банка");
        }

        return ScoringDecision::pass();
    }
}
