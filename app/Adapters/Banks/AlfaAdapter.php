<?php

declare(strict_types=1);

namespace App\Adapters\Banks;

use App\Adapters\AdapterConfig;
use App\Adapters\BankAdapterHelpers;
use App\Adapters\Contracts\BankAdapter;
use App\Data\LeadData;
use App\Data\ScoreResult;
use App\Data\SendResult;
use App\Data\StatusResult;
use App\Http\BankHttpClient;
use App\Http\RetryPolicy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Альфа-Банк integration.
 *
 * Score + send + checkStatus on a single REST endpoint with bearer-token auth.
 * Adapter is intentionally thin: the only logic specific to the bank is the
 * request shape and the response parsing. All retry/backoff/alerting
 * goes through `BankHttpClient`.
 */
class AlfaAdapter implements BankAdapter
{
    use BankAdapterHelpers;

    public function __construct(
        private readonly AdapterConfig $config,
        private readonly BankHttpClient $bankHttp,
    ) {
    }

    public function systemName(): string
    {
        return 'alfa';
    }

    public function displayName(): string
    {
        return 'Альфа-Банк';
    }

    public function score(LeadData $lead): ScoreResult
    {
        $this->assertConfigured();
        $payload = $this->payload($lead);

        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/api/v1/leads/score',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/api/v1/leads/score', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return ScoreResult::failed($response->failureLabel('Alfa score'));
        }

        $body = $response->body ?? [];
        if (! ($body['approved'] ?? false)) {
            return ScoreResult::rejected((string) ($body['reason'] ?? 'rejected'));
        }

        return ScoreResult::ok(
            (string) $body['id'],
            isset($body['score']) ? (float) $body['score'] : null,
        );
    }

    public function send(LeadData $lead): SendResult
    {
        $this->assertConfigured();
        $payload = $this->payload($lead);

        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/api/v1/leads',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/api/v1/leads', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return SendResult::failed($response->failureLabel('Alfa send'));
        }

        return SendResult::ok((string) $response->json('id'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $this->assertConfigured();

        $response = $this->bankHttp->withRetry(
            method: 'GET',
            url: "/api/v1/leads/{$externalId}",
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->get("/api/v1/leads/{$externalId}"),
        );

        return new StatusResult(
            status: (string) ($response->body['status'] ?? StatusResult::ERROR),
            message: $response->body['message'] ?? null,
            raw: $response->body ?? [],
        );
    }

    /** @return array<string, mixed> */
    private function payload(LeadData $lead): array
    {
        return [
            'inn'     => $lead->inn,
            'phone'   => $lead->phone,
            'email'   => $lead->email,
            'name'    => trim(($lead->lastName ?? '').' '.($lead->firstName ?? '').' '.($lead->middleName ?? '')),
            'company' => $lead->companyName,
            'city'    => $lead->city,
            'okved'   => $lead->okved,
        ];
    }

    private function makeRequest(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\AlfaConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withToken($config->apiKey)
            ->acceptJson();
    }

    private function assertConfigured(): void
    {
        /** @var AdapterConfig&\App\Adapters\Configs\AlfaConfig $config */
        $config = $this->config;
        if ($config->apiUrl === '' || $config->apiKey === '') {
            throw new \RuntimeException('AlfaAdapter is not configured (api_url/api_key missing)');
        }
    }
}
