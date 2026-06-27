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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Альфа-Банк integration.
 *
 * Score + send + checkStatus on a single REST endpoint with bearer-token auth.
 * Adapter is intentionally thin: the only logic specific to the bank is the
 * request shape and the response parsing.
 */
class AlfaAdapter implements BankAdapter
{
    use BankAdapterHelpers;

    public function __construct(
        private readonly AdapterConfig $config,
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
        $response = $this->http()->post('/api/v1/leads/score', $this->payload($lead));
        if (! $response->successful()) {
            return ScoreResult::failed("Alfa score HTTP {$response->status()}");
        }

        $body = $response->json();

        return ($body['approved'] ?? false)
            ? ScoreResult::ok((string) $body['id'], isset($body['score']) ? (float) $body['score'] : null)
            : ScoreResult::rejected((string) ($body['reason'] ?? 'rejected'));
    }

    public function send(LeadData $lead): SendResult
    {
        $response = $this->http()->post('/api/v1/leads', $this->payload($lead));
        if (! $response->successful()) {
            return SendResult::failed("Alfa send HTTP {$response->status()}");
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
            'name'    => trim(($lead->lastName ?? '').' '.($lead->firstName ?? '').' '.($lead->middleName ?? '')),
            'company' => $lead->companyName,
            'city'    => $lead->city,
            'okved'   => $lead->okved,
        ];
    }

    private function http(): PendingRequest
    {
        /** @var AdapterConfig&\App\Adapters\Configs\AlfaConfig $config */
        $config = $this->config;
        $this->assertConfigured();

        return Http::baseUrl($config->apiUrl)
            ->timeout($config->timeoutSeconds)
            ->withToken($config->apiKey)
            ->acceptJson();
    }

    private function assertConfigured(): void
    {
        if ($this->config->apiUrl === '' || $this->config->apiKey === '') {
            throw new \RuntimeException('AlfaAdapter is not configured (api_url/api_key missing)');
        }
    }
}
