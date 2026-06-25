<?php

declare(strict_types=1);

namespace App\Adapters\Banks;

use App\Adapters\AdapterConfig;
use App\Adapters\Contracts\BankAdapter;
use App\Data\LeadData;
use App\Data\ScoreResult;
use App\Data\SendResult;
use App\Data\StatusResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Промсвязьбанк integration.
 * Auth: email+password → bearer token cached for the request lifecycle.
 */
class PsbAdapter implements BankAdapter
{
    private ?string $token = null;

    public function __construct(
        private readonly AdapterConfig $config,
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
        $response = $this->http()->post('/fo/v1.0.0/leads/score', $this->payload($lead));
        if (! $response->successful()) {
            return ScoreResult::failed("PSB score HTTP {$response->status()}");
        }

        $body = $response->json();

        return ($body['result'] ?? 'reject') === 'accept'
            ? ScoreResult::ok((string) $body['leadId'])
            : ScoreResult::rejected((string) ($body['comment'] ?? 'rejected'));
    }

    public function send(LeadData $lead): SendResult
    {
        $response = $this->http()->post('/fo/v1.0.0/leads', $this->payload($lead));
        if (! $response->successful()) {
            return SendResult::failed("PSB send HTTP {$response->status()}");
        }

        return SendResult::ok((string) $response->json('leadId'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $response = $this->http()->get("/fo/v1.0.0/leads/{$externalId}");
        $body = $response->json() ?? [];

        return new StatusResult(
            status: $this->mapStatus((string) ($body['status'] ?? '')),
            message: $body['comment'] ?? null,
            raw: $body,
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
            'inn'     => $lead->inn,
            'phone'   => $lead->phone,
            'name'    => $lead->companyName ?? trim(($lead->lastName ?? '').' '.($lead->firstName ?? '')),
            'city'    => $lead->city,
        ];
    }

    private function http(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\PsbConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withToken($this->token ?? $this->authenticate())
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

        $this->token = (string) $response->json('access_token');

        return $this->token;
    }
}
