<?php

declare(strict_types=1);

namespace App\Scoring\Rules;

use App\Data\LeadData;
use App\Models\Lead;
use App\Scoring\Contracts\ScoringRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;

/**
 * Reject the lead when the same ИНН is already in our leads table.
 *
 * TellFax `models/scoring/Alfa::check()` calls this `skip_exist = yes`.
 * In the multi-user world we scope to the same `user_id` so two tenants
 * with overlapping ИННs don't trip each other.
 */
final class SkipExistingRule implements ScoringRule
{
    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision
    {
        if (! $tune->skipExisting) {
            return ScoringDecision::pass();
        }

        $query = Lead::query()->where('inn', $lead->inn);
        if ($lead->userId !== null) {
            $query->where('user_id', $lead->userId);
        }

        if ($query->exists()) {
            return ScoringDecision::duplicate("ИНН {$lead->inn} уже есть в базе этого пользователя");
        }

        return ScoringDecision::pass();
    }
}
