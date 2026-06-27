<?php

declare(strict_types=1);

namespace App\Scoring\Rules;

use App\Data\LeadData;
use App\Models\Lead;
use App\Scoring\Contracts\ScoringRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;
use Carbon\CarbonImmutable;

/**
 * Reject the lead when the same ИНН was processed within the last
 * `duplicateDays` days. Prevents spamming banks with the same lead
 * inside the configured cooldown window.
 *
 * TellFax `models/scoring/Psb::check()` / `models/scoring/Vtb::check()`
 * call this `off_days`. We compare against the lead's `created_at`,
 * not `updated_at`, so re-edits of an old lead don't reset the clock.
 */
final class DuplicatePeriodRule implements ScoringRule
{
    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if ($tune->duplicateDays === null) {
            return ScoringDecision::pass();
        }

        $threshold = CarbonImmutable::now()->subDays($tune->duplicateDays);

        $query = Lead::query()
            ->where('inn', $lead->inn)
            ->where('created_at', '>=', $threshold);

        if ($lead->userId !== null) {
            $query->where('user_id', $lead->userId);
        }

        if ($query->exists()) {
            return ScoringDecision::duplicate(
                "Дубль ИНН {$lead->inn} за последние {$tune->duplicateDays} дн.",
            );
        }

        return ScoringDecision::pass();
    }
}
