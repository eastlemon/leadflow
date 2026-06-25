<?php

declare(strict_types=1);

namespace App\Adapters;

/**
 * Base for typed per-bank config objects.
 *
 * Replaces the legacy Yii2 `connect.tune` JSON blob that every
 * service creator parsed differently. Concrete configs (AlfaConfig,
 * PsbConfig, ...) extend this and add their own typed properties.
 */
abstract class AdapterConfig
{
    public function __construct(
        public readonly string $systemName,
        public readonly string $displayName,
        public readonly string $apiUrl,
        public readonly bool $enabled = true,
        public readonly int $timeoutSeconds = 30,
        public readonly int $retryAttempts = 3,
        public readonly int $retryBackoffSeconds = 5,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
