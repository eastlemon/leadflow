<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Adapters\AdapterRegistry;
use App\Data\LeadData;
use App\Models\Lead;
use App\Models\LeadJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendLeadJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $leadId,
        public readonly string $systemName,
    ) {
    }

    public function handle(AdapterRegistry $registry): void
    {
        $lead = Lead::find($this->leadId);
        if (! $lead) {
            Log::warning('SendLeadJob: lead not found', ['lead_id' => $this->leadId]);

            return;
        }

        $job = LeadJob::create([
            'lead_id'     => $lead->id,
            'system_name' => $this->systemName,
            'stage'       => LeadJob::STAGE_SEND,
            'status'      => LeadJob::STATUS_PROCESSING,
        ]);

        try {
            $adapter = $registry->get($this->systemName);
            $result  = $adapter->send(LeadData::from($lead->toArray()));

            $job->update([
                'status'      => $result->success ? LeadJob::STATUS_OK : LeadJob::STATUS_FAILED,
                'external_id' => $result->externalId,
                'error'       => $result->message,
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
