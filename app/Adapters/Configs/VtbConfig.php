<?php

declare(strict_types=1);

namespace App\Adapters\Configs;

use App\Adapters\AdapterConfig;
use App\Scoring\ScoringConfig;

class VtbConfig extends AdapterConfig
{
    public function __construct(
        string $apiUrl,
        public readonly string $authUrl,
        public readonly string $clientId,
        public readonly string $clientSecret,
        bool $enabled = true,
        int $timeoutSeconds = 30,
        int $retryAttempts = 3,
        int $retryBackoffSeconds = 5,
        ?ScoringConfig $scoring = null,
    ) {
        parent::__construct(
            systemName: 'vtb',
            displayName: 'ВТБ',
            apiUrl: $apiUrl,
            enabled: $enabled,
            timeoutSeconds: $timeoutSeconds,
            retryAttempts: $retryAttempts,
            retryBackoffSeconds: $retryBackoffSeconds,
            scoring: $scoring,
        );
    }
}
