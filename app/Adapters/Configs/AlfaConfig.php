<?php

declare(strict_types=1);

namespace App\Adapters\Configs;

use App\Adapters\AdapterConfig;
use App\Scoring\ScoringConfig;

class AlfaConfig extends AdapterConfig
{
    public function __construct(
        string $apiUrl,
        public readonly string $apiKey,
        bool $enabled = true,
        int $timeoutSeconds = 30,
        int $retryAttempts = 3,
        int $retryBackoffSeconds = 5,
        ?ScoringConfig $scoring = null,
    ) {
        parent::__construct(
            systemName: 'alfa',
            displayName: 'Альфа-Банк',
            apiUrl: $apiUrl,
            enabled: $enabled,
            timeoutSeconds: $timeoutSeconds,
            retryAttempts: $retryAttempts,
            retryBackoffSeconds: $retryBackoffSeconds,
            scoring: $scoring,
        );
    }
}
