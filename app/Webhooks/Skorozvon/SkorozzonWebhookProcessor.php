<?php

declare(strict_types=1);

namespace App\Webhooks\Skorozvon;

use App\Adapters\AdapterRegistry;
use App\Data\LeadData;
use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

/**
 * Receives a Скорозвон lead payload, persists it, and fans out
 * one ScoreLeadJob per active bank.
 *
 * The WebhookClient machinery gives us:
 *  - signature verification
 *  - storage of the raw payload in `webhook_calls`
 *  - automatic retry on exceptions
 */
class SkorozzonWebhookProcessor extends ProcessWebhookJob
{
    public function handle(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->webhookCall->payload;

        $lead = Lead::create([
            'inn'          => (string) ($payload['inn'] ?? ''),
            'phone'        => $payload['phone']   ?? null,
            'email'        => $payload['email']   ?? null,
            'first_name'   => $payload['first_name']  ?? null,
            'last_name'    => $payload['last_name']   ?? null,
            'middle_name'  => $payload['middle_name'] ?? null,
            'company_name' => $payload['company'] ?? null,
            'city'         => $payload['city']    ?? null,
            'region'       => $payload['region']  ?? null,
            'okved'        => $payload['okved']   ?? null,
            'extra'        => $payload,
            'source'       => 'skorozvon',
        ]);

        $registry = app(AdapterRegistry::class);
        foreach (array_keys($registry->available()) as $systemName) {
            ScoreLeadJob::dispatch($lead->id, $systemName);
        }
    }
}
