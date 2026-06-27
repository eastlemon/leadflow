<?php

declare(strict_types=1);

namespace App\Scoring\Rules;

use App\Data\LeadData;
use App\Scoring\Contracts\ScoringRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;
use App\Services\KeyDetector;

/**
 * Reject the lead when its ОКВЭД is in the bank's blacklist.
 *
 * Port of `models/scoring/Alfa::check()` — only Alfa uses an ОКВЭД
 * blacklist in the TellFax code today, but the rule is generic so any
 * bank can opt in via `okved_skip_list` in its tune.
 *
 * A missing ОКВЭД on the lead is NOT a rejection — the bank can still
 * decide for itself. Empty blacklist means the rule is a no-op.
 */
final class OkvedBlacklistRule implements ScoringRule
{
    public function __construct(
        private readonly KeyDetector $keys,
    ) {
    }

    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if ($tune->okvedBlacklist === [] || $lead->okved === null || $lead->okved === '') {
            return ScoringDecision::pass();
        }

        if (! $this->keys->isAllowedByBlackList($lead->okved, $tune->okvedBlacklist)) {
            return ScoringDecision::rejected('okved_blacklist', "ОКВЭД {$lead->okved} в чёрном списке банка");
        }

        return ScoringDecision::pass();
    }
}
