<?php

declare(strict_types=1);

namespace App\Scoring\Contracts;

use App\Data\LeadData;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;

/**
 * One pre-flight rule. Returns either PASS or a non-pass decision.
 *
 * Stateless rules only need $lead and $tune. Rules that touch the DB
 * (SkipExisting, DuplicatePeriod) take their DB dependency via the
 * service-layer constructor — the interface stays slim.
 */
interface ScoringRule
{
    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision;
}
