<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\BankAdapter;
use RuntimeException;

/**
 * Explicit, hand-curated map from system_name to adapter class.
 *
 * Replaces the legacy `ServiceCreator` that switched on `sys_name`
 * and returned different objects for the aping/scoring/tf/sk layers.
 * Here every bank is one class, one place to look.
 */
class AdapterRegistry
{
    /** @var array<string, class-string<BankAdapter>> */
    private const MAP = [
        'alfa'  => \App\Adapters\Banks\AlfaAdapter::class,
        'psb'   => \App\Adapters\Banks\PsbAdapter::class,
        'vtb'   => \App\Adapters\Banks\VtbAdapter::class,
        'ural'  => \App\Adapters\Banks\UralAdapter::class,
    ];

    /**
     * @param  array<string, array<string, mixed>>  $settingsBySystem
     */
    public function __construct(
        private readonly ConfigFactory $configFactory,
        private readonly array $settingsBySystem = [],
    ) {
    }

    public function has(string $systemName): bool
    {
        return isset(self::MAP[$systemName]);
    }

    /**
     * @return array<string, class-string<BankAdapter>>
     */
    public function available(): array
    {
        return self::MAP;
    }

    public function get(string $systemName): BankAdapter
    {
        if (! $this->has($systemName)) {
            throw new RuntimeException("No adapter registered for system_name: {$systemName}");
        }

        $class = self::MAP[$systemName];
        $settings = $this->settingsBySystem[$systemName] ?? ['system_name' => $systemName];
        $config = $this->configFactory->fromArray($settings);

        return new $class($config);
    }
}
