<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Scoring\ScoringConfig;

/**
 * Base for typed per-bank config objects.
 *
 * Replaces the legacy Yii2 `connect.tune` JSON blob that every
 * service creator parsed differently. Concrete configs (AlfaConfig,
 * PsbConfig, ...) extend this and add their own typed properties.
 *
 * Carries both the API-side config (URL, keys, retry policy) AND
 * the pre-flight `ScoringConfig` so a single object fully describes
 * how a (user, bank) pair is wired.
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
        public readonly ?ScoringConfig $scoring = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function scoring(): ScoringConfig
    {
        return $this->scoring ?? ScoringConfig::default();
    }
}
