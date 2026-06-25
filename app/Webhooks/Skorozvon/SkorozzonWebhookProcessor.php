<?php

declare(strict_types=1);

namespace App\Webhooks\Skorozvon;

use App\Adapters\AdapterRegistry;
use App\Data\LeadData;
use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Services\KeyDetector;
use Illuminate\Support\Facades\Log;
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

        $inn = (string) ($payload['inn'] ?? '');
        if (! app(KeyDetector::class)->validInn($inn)) {
            Log::warning('SkorozzonWebhookProcessor: dropping payload with invalid INN', [
                'inn' => $inn,
                'id'  => $this->webhookCall->id,
            ]);
            return; // tell Spatie the call is processed so it doesn't retry
        }

        $phone = $payload['phone'] ?? null;
        if ($phone !== null && ! app(KeyDetector::class)->validPhone((string) $phone)) {
            Log::info('SkorozzonWebhookProcessor: dropping invalid phone, keeping lead', [
                'phone' => $phone,
            ]);
            $phone = null;
        }

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
            'user_id'      => $this->resolveUserId($payload),
        ]);

        $registry = app(AdapterRegistry::class);
        if ($lead->user_id !== null) {
            // Multi-user fan-out: only banks this user actually has an active connection for.
            foreach (array_keys($registry->allForUser($lead->user_id)) as $systemName) {
                ScoreLeadJob::dispatch($lead->id, $systemName);
            }
        } else {
            // No user attached — fall back to the global bank list (admin/system-level connects).
            foreach (array_keys($registry->available()) as $systemName) {
                ScoreLeadJob::dispatch($lead->id, $systemName);
            }
        }
    }

    /**
     * Best-effort user resolution from the payload.
     * Скорозвон usually passes either `user_id`, `user_email`, or the call's `client_id`.
     *
     * @param array<string, mixed> $payload
     */
    private function resolveUserId(array $payload): ?int
    {
        if (isset($payload['user_id']) && is_numeric($payload['user_id'])) {
            return (int) $payload['user_id'];
        }

        $email = $payload['user_email'] ?? $payload['client_email'] ?? null;
        if (is_string($email) && $email !== '') {
            $user = \App\Models\User::query()->where('email', $email)->first();
            if ($user) {
                return $user->id;
            }
        }

        return null;
    }
}
