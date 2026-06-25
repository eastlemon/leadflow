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
use Illuminate\Support\Facades\Cache;

/**
 * ВТБ integration.
 * Auth: OAuth2 client_credentials. Token is cached for 50 minutes
 * (VТБ access tokens are valid for 60 min, refresh a bit early).
 */
class VtbAdapter implements BankAdapter
{
    public function __construct(
        private readonly AdapterConfig $config,
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
        $response = $this->http()->post('/openapi/smb/lecs/lead-impers/v1/score', $this->payload($lead));
        if (! $response->successful()) {
            return ScoreResult::failed("VTB score HTTP {$response->status()}");
        }

        $body = $response->json();

        return ($body['decision'] ?? 'reject') === 'approve'
            ? ScoreResult::ok((string) $body['leadId'], isset($body['score']) ? (float) $body['score'] : null)
            : ScoreResult::rejected((string) ($body['rejectReason'] ?? 'rejected'));
    }

    public function send(LeadData $lead): SendResult
    {
        $response = $this->http()->post('/openapi/smb/lecs/lead-impers/v1/', $this->payload($lead));
        if (! $response->successful()) {
            return SendResult::failed("VTB send HTTP {$response->status()}");
        }

        return SendResult::ok((string) $response->json('leadId'));
    }

    public function checkStatus(string $externalId): StatusResult
    {
        $response = $this->http()->get("/openapi/smb/lecs/lead-impers/v1/{$externalId}");
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
            'inn'     => $lead->inn,
            'phone'   => $lead->phone,
            'email'   => $lead->email,
            'firstName'  => $lead->firstName,
            'lastName'   => $lead->lastName,
            'middleName' => $lead->middleName,
            'companyName' => $lead->companyName,
            'city'    => $lead->city,
            'region'  => $lead->region,
            'okved'   => $lead->okved,
        ];
    }

    private function http(): PendingRequest
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
