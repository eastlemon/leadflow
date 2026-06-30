<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

use App\Data\LeadData;
use App\Data\ScoreResult;
use App\Data\SendResult;
use App\Data\StatusResult;
use App\Scoring\ScoringConfig;

/**
 * Contract every bank integration must implement.
 *
 * The Adapter Pattern collapses the per-bank 5-layer split
 * (aping/scoring/tf/sk/reporting) from the legacy Yii2 code
 * into a single object that the rest of the system talks to.
 *
 * Implementations are responsible for talking to the bank API,
 * translating wire formats, and reporting structured results.
 *
 * `scoringConfig()` exposes the per-bank pre-flight tune so the
 * `ScoreLeadJob` can run local rules before paying for the bank
 * score API. The default implementation returns a permissive
 * config (all rules inactive); adapters that need it inject the
 * real config from the user's `tune` blob.
 */
interface BankAdapter
{
    /**
     * System name used to look the adapter up in the registry,
     * e.g. "alfa", "psb", "vtb", "ural".
     */
    public function systemName(): string;

    /**
     * Human-readable label, used in admin UI.
     */
    public function displayName(): string;

    /**
     * Pre-flight configuration for this bank, parsed from the
     * user's `tune` JSON. Defaults to permissive when the
     * adapter doesn't carry a ScoringConfig.
     */
    public function scoringConfig(): ScoringConfig;

    /**
     * Run a scoring/prequalification pass on the lead.
     * Returns success/failure with a bank-side score/id when available.
     */
    public function score(LeadData $lead): ScoreResult;

    /**
     * Submit the lead for actual processing.
     */
    public function send(LeadData $lead): SendResult;

    /**
     * Check the current status of a previously-sent lead.
     */
    public function checkStatus(string $externalId): StatusResult;

    /**
     * Schema for the Filament admin form that edits this bank's tune.
     *
     * Each key is a field name (matching the JSON key in `tune`),
     * and the value describes the field type, label, and whether
     * it's required. The admin UI renders this dynamically.
     *
     * @return array<string, array{type: string, label: string, required?: bool, default?: mixed, hint?: string}>
     */
    public static function configSchema(): array;
}
