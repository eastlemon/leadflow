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
 * Урал integration.
 * Simple REST + x-api-key header.
 */
class UralAdapter implements BankAdapter
{
    use BankAdapterHelpers;

    public function __construct(
        private readonly AdapterConfig $config,
        private readonly BankHttpClient $bankHttp,
    ) {
    }

    public function systemName(): string
    {
        return 'ural';
    }

    public function displayName(): string
    {
        return 'Урал';
    }

    public function score(LeadData $lead): ScoreResult
    {
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
            return ScoreResult::failed($response->failureLabel('Ural score'));
        }

        $body = $response->body ?? [];
        if (! ($body['approved'] ?? false)) {
            return ScoreResult::rejected((string) ($body['reason'] ?? 'rejected'));
        }

        return ScoreResult::ok((string) $body['id']);
    }

    public function send(LeadData $lead): SendResult
    {
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
            return SendResult::failed($response->failureLabel('Ural send'));
        }

        return SendResult::ok((string) $response->json('id'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
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
            'company' => $lead->companyName,
            'city'    => $lead->city,
        ];
    }

    private function makeRequest(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\UralConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withHeaders(['X-Api-Key' => $config->apiKey])
            ->acceptJson();
    }
}
