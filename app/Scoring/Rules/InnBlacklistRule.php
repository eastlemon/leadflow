<?php

declare(strict_types=1);

namespace App\Scoring\Rules;

use App\Data\LeadData;
use App\Scoring\Contracts\ScoringRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;
use App\Services\KeyDetector;

/**
 * Reject the lead when its ИНН (or an ИНН prefix) appears in the
 * bank's blacklist.
 *
 * Mirrors `TellFax` `models/scoring/Alfa::check()` /
 * `models/scoring/Psb::check()` etc, where `inn_skip_list` is a
 * newline- or comma-separated string and prefix match is used so
 * a 5-digit prefix of an ИНН is enough to flag it.
 */
final class InnBlacklistRule implements ScoringRule
{
    public function __construct(
        private readonly KeyDetector $keys,
    ) {
    }

    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if ($tune->innBlacklist === []) {
            return ScoringDecision::pass();
        }

        if (! $this->keys->isAllowedByBlackList($lead->inn, $tune->innBlacklist)) {
            return ScoringDecision::rejected('inn_blacklist', "ИНН {$lead->inn} в чёрном списке банка");
        }

        return ScoringDecision::pass();
    }
}
