<?php

declare(strict_types=1);

namespace App\Scoring;

use App\Data\LeadData;
use App\Scoring\Contracts\ScoringRule;

/**
 * Runs a lead through every configured rule in order and returns the
 * first non-pass decision. If everything passes, returns PASS and the
 * caller is free to call the bank API.
 *
 * Per-bank rule composition lives in `ScoringConfigFactory::forBank()`
 * — this class is intentionally bank-agnostic so it stays easy to test.
 */
final class BankScoringService
{
    /**
     * @param  list<ScoringRule>  $rules
     */
    public function __construct(
        private readonly array $rules,
    ) {
    }

    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if (! $tune->enabled) {
            return ScoringDecision::disabled();
        }

        foreach ($this->rules as $rule) {
            $decision = $rule->check($lead, $tune);
            if (! $decision->isPass()) {
                return $decision;
            }
        }

        return ScoringDecision::pass();
    }
}
