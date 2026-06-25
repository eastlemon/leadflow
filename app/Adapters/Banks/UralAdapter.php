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
 * Урал integration.
 * Simple REST + x-api-key header.
 */
class UralAdapter implements BankAdapter
{
    public function __construct(
        private readonly AdapterConfig $config,
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
        $response = $this->http()->post('/api/v1/leads/score', $this->payload($lead));
        if (! $response->successful()) {
            return ScoreResult::failed("Ural score HTTP {$response->status()}");
        }

        $body = $response->json();

        return ($body['approved'] ?? false)
            ? ScoreResult::ok((string) $body['id'])
            : ScoreResult::rejected((string) ($body['reason'] ?? 'rejected'));
    }

    public function send(LeadData $lead): SendResult
    {
        $response = $this->http()->post('/api/v1/leads', $this->payload($lead));
        if (! $response->successful()) {
            return SendResult::failed("Ural send HTTP {$response->status()}");
        }

        return SendResult::ok((string) $response->json('id'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $response = $this->http()->get("/api/v1/leads/{$externalId}");
        $body = $response->json() ?? [];

        return new StatusResult(
            status: (string) ($body['status'] ?? StatusResult::ERROR),
            message: $body['message'] ?? null,
            raw: $body,
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

    private function http(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\UralConfig $config */
        $config = $this->config;

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withHeaders(['X-Api-Key' => $config->apiKey])
            ->acceptJson();
    }
}
