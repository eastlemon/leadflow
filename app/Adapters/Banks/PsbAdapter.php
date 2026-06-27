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
 * Промсвязьбанк integration.
 * Auth: email+password → bearer token. Token is re-fetched on every
 * `makeRequest()` call so a retry after expiry starts a fresh session.
 */
class PsbAdapter implements BankAdapter
{
    use BankAdapterHelpers;

    public function __construct(
        private readonly AdapterConfig $config,
        private readonly BankHttpClient $bankHttp,
    ) {
    }

    public function systemName(): string
    {
        return 'psb';
    }

    public function displayName(): string
    {
        return 'Промсвязьбанк';
    }

    public function score(LeadData $lead): ScoreResult
    {
        $payload = $this->payload($lead);
        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/fo/v1.0.0/leads/score',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/fo/v1.0.0/leads/score', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return ScoreResult::failed($response->failureLabel('PSB score'));
        }

        $body = $response->body ?? [];
        if (($body['result'] ?? 'reject') !== 'accept') {
            return ScoreResult::rejected((string) ($body['comment'] ?? 'rejected'));
        }

        return ScoreResult::ok((string) $body['leadId']);
    }

    public function send(LeadData $lead): SendResult
    {
        $payload = $this->payload($lead);
        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/fo/v1.0.0/leads',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/fo/v1.0.0/leads', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return SendResult::failed($response->failureLabel('PSB send'));
        }

        return SendResult::ok((string) $response->json('leadId'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $response = $this->bankHttp->withRetry(
            method: 'GET',
            url: "/fo/v1.0.0/leads/{$externalId}",
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->get("/fo/v1.0.0/leads/{$externalId}"),
        );

        return new StatusResult(
            status: $this->mapStatus((string) ($response->body['status'] ?? '')),
            message: $response->body['comment'] ?? null,
            raw: $response->body ?? [],
        );
    }

    private function mapStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'new', 'created' => StatusResult::NEW,
            'processing', 'in_progress' => StatusResult::PROCESSING,
            'approved', 'accepted' => StatusResult::APPROVED,
            'rejected', 'declined' => StatusResult::REJECTED,
            default => StatusResult::ERROR,
        };
    }

    /** @return array<string, mixed> */
    private function payload(LeadData $lead): array
    {
        return [
            'inn'   => $lead->inn,
            'phone' => $lead->phone,
            'name'  => $lead->companyName ?? trim(($lead->lastName ?? '').' '.($lead->firstName ?? '')),
            'city'  => $lead->city,
        ];
    }

    private function makeRequest(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\PsbConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withToken($this->authenticate())
            ->acceptJson();
    }

    private function authenticate(): string
    {
        /** @var AdapterConfig&\App\Adapters\Configs\PsbConfig $config */
        $config = $this->config;

        $response = Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->asJson()
            ->post('/auth', [
                'email'    => $config->email,
                'password' => $config->password,
            ]);

        return (string) $response->json('access_token');
    }
}
