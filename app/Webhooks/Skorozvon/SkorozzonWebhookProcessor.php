<?php

declare(strict_types=1);

namespace App\Webhooks\Skorozvon;

use App\Adapters\AdapterRegistry;
use App\Data\LeadData;
use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Services\KeyDetector;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

/**
 * Receives a Скорозвон lead payload, persists it, and fans out
 * one ScoreLeadJob per active bank in the matching pipeline.
 *
 * Resolution strategy:
 *   1. If the payload contains a pipeline_id — use that pipeline directly.
 *   2. If the payload contains a user_id/email — find the user's
 *      first active Skorozvon pipeline and use its receivers.
 *   3. Fallback: find any active Skorozvon pipeline and fan out.
 */
class SkorozzonWebhookProcessor extends ProcessWebhookJob
{
    public function handle(): void
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->webhookCall->payload;

        $inn = (string) ($payload['inn'] ?? '');
        if (! app(KeyDetector::class)->validInn($inn)) {
            Log::warning('SkorozzonWebhookProcessor: dropping payload with invalid INN', [
                'inn' => $inn,
                'id'  => $this->webhookCall->id,
            ]);
            return;
        }

        $phone = $payload['phone'] ?? null;
        if ($phone !== null && ! app(KeyDetector::class)->validPhone((string) $phone)) {
            Log::info('SkorozzonWebhookProcessor: dropping invalid phone, keeping lead', [
                'phone' => $phone,
            ]);
            $phone = null;
        }

        // Resolve the pipeline for this webhook.
        $pipeline = $this->resolvePipeline($payload);
        $user = $this->resolveUser($payload);

        $lead = Lead::create([
            'inn'          => $inn,
            'phone'        => $phone,
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
            'user_id'      => $user?->id,
            'pipeline_id'  => $pipeline?->id,
        ]);

        if ($pipeline) {
            // Fan out to this pipeline's active receivers.
            $receiverNames = $pipeline->activeReceiverNames();
            foreach ($receiverNames as $systemName) {
                ScoreLeadJob::dispatch($lead->id, $systemName);
            }
        } else {
            // No pipeline found — fan out to all banks for the user
            // (legacy behavior via user_connects).
            $registry = app(AdapterRegistry::class);
            if ($user) {
                foreach (array_keys($registry->allForUser($user->id)) as $systemName) {
                    ScoreLeadJob::dispatch($lead->id, $systemName);
                }
            } else {
                Log::warning('SkorozzonWebhookProcessor: no pipeline and no user, fan-out to all banks', [
                    'lead_id' => $lead->id,
                ]);
                foreach (array_keys($registry->available()) as $systemName) {
                    ScoreLeadJob::dispatch($lead->id, $systemName);
                }
            }
        }
    }

    /**
     * Resolve the pipeline from the payload.
     */
    private function resolvePipeline(array $payload): ?Pipeline
    {
        // Explicit pipeline_id in payload (highest priority).
        if (isset($payload['pipeline_id']) && is_numeric($payload['pipeline_id'])) {
            $pipeline = Pipeline::find((int) $payload['pipeline_id']);
            if ($pipeline && $pipeline->is_active && $pipeline->provider === 'skorozvon') {
                return $pipeline;
            }
        }

        // Find the user's first active Skorozvon pipeline.
        $user = $this->resolveUser($payload);
        if ($user) {
            $pipeline = Pipeline::query()
                ->where('user_id', $user->id)
                ->where('provider', 'skorozvon')
                ->where('is_active', true)
                ->first();
            if ($pipeline) {
                return $pipeline;
            }
        }

        // Fallback: any active Skorozvon pipeline.
        return Pipeline::query()
            ->where('provider', 'skorozvon')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Best-effort user resolution from the payload.
     */
    private function resolveUser(array $payload): ?\App\Models\User
    {
        if (isset($payload['user_id']) && is_numeric($payload['user_id'])) {
            return \App\Models\User::find((int) $payload['user_id']);
        }

        $email = $payload['user_email'] ?? $payload['client_email'] ?? null;
        if (is_string($email) && $email !== '') {
            return \App\Models\User::query()->where('email', $email)->first();
        }

        return null;
    }
}