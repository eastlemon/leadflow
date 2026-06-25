<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

use App\Data\LeadData;
use App\Data\ScoreResult;
use App\Data\SendResult;
use App\Data\StatusResult;

/**
 * Contract every bank integration must implement.
 *
 * The Adapter Pattern collapses the per-bank 5-layer split
 * (aping/scoring/tf/sk/reporting) from the legacy Yii2 code
 * into a single object that the rest of the system talks to.
 *
 * Implementations are responsible for talking to the bank API,
 * translating wire formats, and reporting structured results.
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
}
