<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\BankAdapter;
use App\Scoring\ScoringConfig;

/**
 * Trait shared by every bank adapter. Centralises the trivial
 * `scoringConfig()` accessor so each concrete adapter doesn't
 * have to repeat the null-coalesce.
 *
 * The real `ScoringConfig` lives on the `AdapterConfig` and is
 * exposed via `scoringConfig()`.
 */
trait BankAdapterHelpers
{
    public function scoringConfig(): ScoringConfig
    {
        if (property_exists($this, 'config') && $this->config instanceof AdapterConfig) {
            return $this->config->scoring();
        }

        return ScoringConfig::default();
    }
}
