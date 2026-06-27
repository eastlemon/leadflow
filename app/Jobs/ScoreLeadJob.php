<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Adapters\AdapterRegistry;
use App\Data\LeadData;
use App\Models\Lead;
use App\Models\LeadJob;
use App\Scoring\BankScoringService;
use App\Scoring\ScoringConfigFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Async scoring for one (lead, bank) pair.
 *
 * Replaces the Yii2 scoring pipeline (jobs/ScoringJob + model classes
 * per bank) with a single generic job dispatched with the system name.
 *
 * Two-step score:
 *   1. Local pre-flight (BankScoringService) — blacklist/whitelist/
 *      dedup rules read from the user's tune. No HTTP.
 *   2. Bank-side score API ($adapter->score()) — only if pre-flight
 *      passed. The pre-flight is the cheaper filter so the bank API
 *      is the fallback.
 *
 * LeadJob status semantics:
 *   - PROCESSING : the job is running.
 *   - OK         : the lead was actually evaluated. Could be a
 *                  pre-flight rejection, a bank approval, or a bank
 *                  rejection. The reason goes in `error`.
 *   - FAILED     : transport / config error, including bank 5xx after
 *                  retries.
 */
class ScoreLeadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly int $leadId,
        public readonly string $systemName,
    ) {
    }

    public function handle(
        AdapterRegistry $registry,
        ScoringConfigFactory $scoringConfigFactory,
    ): void {
        $lead = Lead::find($this->leadId);
        if (! $lead) {
            Log::warning('ScoreLeadJob: lead not found', ['lead_id' => $this->leadId]);

            return;
        }

        $adapter = $lead->user_id
            ? $registry->getForUser($lead->user_id, $this->systemName)
            : null;

        if (! $adapter) {
            Log::info('ScoreLeadJob: user has no active connection, skipping', [
                'lead_id'     => $lead->id,
                'system_name' => $this->systemName,
                'user_id'     => $lead->user_id,
            ]);

            return;
        }

        $job = LeadJob::create([
            'lead_id'     => $lead->id,
            'system_name' => $this->systemName,
            'stage'       => LeadJob::STAGE_SCORE,
            'status'      => LeadJob::STATUS_PROCESSING,
        ]);

        $leadData = LeadData::from($lead->toArray());

        // --- Pre-flight ----------------------------------------------------
        // Reuse the adapter's ScoringConfig (carried on its AdapterConfig)
        // and rebuild the same rule list the factory would. The rules
        // themselves are stateless + cheap to construct; the heavy part
        // is the typed config which the adapter already holds.
        $scoringCfg = $adapter->scoringConfig();
        ['rules' => $rules] = $scoringConfigFactory->forBank(
            $this->systemName,
            [], // tune already folded into AdapterConfig by ConfigFactory
        );

        $service = new BankScoringService($rules);
        $decision = $service->check($leadData, $scoringCfg);

        if ($decision->blocksSend()) {
            $job->update([
                'status'      => LeadJob::STATUS_OK,
                'error'       => $decision->reason,
                'finished_at' => now(),
            ]);

            Log::info('ScoreLeadJob: pre-flight blocked the lead', [
                'lead_id'     => $lead->id,
                'system_name' => $this->systemName,
                'decision'    => $decision->status,
                'code'        => $decision->code,
                'reason'      => $decision->reason,
            ]);

            return;
        }

        // --- Bank API ------------------------------------------------------
        try {
            $result = $adapter->score($leadData);

            $job->update([
                'status'      => $result->success ? LeadJob::STATUS_OK : LeadJob::STATUS_FAILED,
                'external_id' => $result->externalId,
                'error'       => $result->reason,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status'      => LeadJob::STATUS_FAILED,
                'error'       => $e->getMessage(),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }
}
