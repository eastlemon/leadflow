<?php

declare(strict_types=1);

namespace App\Adapters\Configs;

use App\Adapters\AdapterConfig;

class PsbConfig extends AdapterConfig
{
    public function __construct(
        string $apiUrl,
        public readonly string $email,
        public readonly string $password,
        bool $enabled = true,
        int $timeoutSeconds = 30,
        int $retryAttempts = 3,
        int $retryBackoffSeconds = 5,
    ) {
        parent::__construct(
            systemName: 'psb',
            displayName: 'Промсвязьбанк',
            apiUrl: $apiUrl,
            enabled: $enabled,
            timeoutSeconds: $timeoutSeconds,
            retryAttempts: $retryAttempts,
            retryBackoffSeconds: $retryBackoffSeconds,
        );
    }
}
