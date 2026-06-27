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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * ВТБ integration.
 * Auth: OAuth2 client_credentials. Token is cached for 50 minutes
 * (VTB access tokens are valid for 60 min, refresh a bit early).
 */
class VtbAdapter implements BankAdapter
{
    use BankAdapterHelpers;

    public function __construct(
        private readonly AdapterConfig $config,
        private readonly BankHttpClient $bankHttp,
    ) {
    }

    public function systemName(): string
    {
        return 'vtb';
    }

    public function displayName(): string
    {
        return 'ВТБ';
    }

    public function score(LeadData $lead): ScoreResult
    {
        $payload = $this->payload($lead);
        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/openapi/smb/lecs/lead-impers/v1/score',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/openapi/smb/lecs/lead-impers/v1/score', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return ScoreResult::failed($response->failureLabel('VTB score'));
        }

        $body = $response->body ?? [];
        if (($body['decision'] ?? 'reject') !== 'approve') {
            return ScoreResult::rejected((string) ($body['rejectReason'] ?? 'rejected'));
        }

        return ScoreResult::ok(
            (string) $body['leadId'],
            isset($body['score']) ? (float) $body['score'] : null,
        );
    }

    public function send(LeadData $lead): SendResult
    {
        $payload = $this->payload($lead);
        $response = $this->bankHttp->withRetry(
            method: 'POST',
            url: '/openapi/smb/lecs/lead-impers/v1/',
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->post('/openapi/smb/lecs/lead-impers/v1/', $payload),
            payload: $payload,
        );

        if (! $response->successful) {
            return SendResult::failed($response->failureLabel('VTB send'));
        }

        return SendResult::ok((string) $response->json('leadId'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $response = $this->bankHttp->withRetry(
            method: 'GET',
            url: "/openapi/smb/lecs/lead-impers/v1/{$externalId}",
            systemName: $this->systemName(),
            policy: RetryPolicy::fromConfig($this->config),
            action: fn () => $this->makeRequest()->get("/openapi/smb/lecs/lead-impers/v1/{$externalId}"),
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
            'new' => StatusResult::NEW,
            'in_progress', 'processing' => StatusResult::PROCESSING,
            'approved' => StatusResult::APPROVED,
            'rejected' => StatusResult::REJECTED,
            default => StatusResult::ERROR,
        };
    }

    /** @return array<string, mixed> */
    private function payload(LeadData $lead): array
    {
        return [
            'inn'         => $lead->inn,
            'phone'       => $lead->phone,
            'email'       => $lead->email,
            'firstName'   => $lead->firstName,
            'lastName'    => $lead->lastName,
            'middleName'  => $lead->middleName,
            'companyName' => $lead->companyName,
            'city'        => $lead->city,
            'region'      => $lead->region,
            'okved'       => $lead->okved,
        ];
    }

    private function makeRequest(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\VtbConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withToken($this->token())
            ->acceptJson();
    }

    private function token(): string
    {
        /** @var AdapterConfig&\App\Adapters\Configs\VtbConfig $config */
        $config = $this->config;

        return Cache::remember('vtb:oauth:token', 3000, function () use ($config) {
            $response = Http::baseUrl($config->authUrl)
                ->timeout($config->timeoutSeconds)
                ->asForm()
                ->post('/passport/oauth2/token', [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $config->clientId,
                    'client_secret' => $config->clientSecret,
                ]);

            return (string) $response->json('access_token', '');
        });
    }
}
