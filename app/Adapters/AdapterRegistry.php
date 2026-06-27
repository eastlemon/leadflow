<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\BankAdapter;
use App\Models\UserConnect;
use RuntimeException;

/**
 * Explicit, hand-curated map from system_name to adapter class.
 *
 * Replaces the legacy `ServiceCreator` that switched on `sys_name`
 * and returned different objects for the aping/scoring/tf/sk layers.
 * Here every bank is one class, one place to look.
 *
 * Multi-user aware: `getForUser()` reads per-user credentials from
 * `user_connects` so each user uses their own bank API keys.
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

    public function __construct(
        private readonly ConfigFactory $configFactory,
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

    /**
     * Builds an adapter from an arbitrary settings array.
     * Used by tests and by environments where no user owns the row yet.
     *
     * @param array<string, mixed> $settings
     */
    public function get(string $systemName, array $settings = []): BankAdapter
    {
        if (! $this->has($systemName)) {
            throw new RuntimeException("No adapter registered for system_name: {$systemName}");
        }

        $class = self::MAP[$systemName];
        $settings = $settings !== [] ? $settings : ['system_name' => $systemName];
        $config = $this->configFactory->fromArray($settings);

        // Resolve via the container so the adapter's other constructor
        // args (BankHttpClient, ...) get injected automatically.
        return app($class, ['config' => $config]);
    }

    /**
     * Builds an adapter with credentials pulled from a specific user's
     * `user_connects` row. Returns null if the user has no active row
     * for this bank — callers should skip silently, not throw.
     */
    public function getForUser(int $userId, string $systemName): ?BankAdapter
    {
        $row = UserConnect::query()
            ->where('user_id', $userId)
            ->where('system_name', $systemName)
            ->where('is_active', true)
            ->first();

        if (! $row) {
            return null;
        }

        $settings = array_merge((array) $row->tune, ['system_name' => $systemName]);

        return $this->get($systemName, $settings);
    }

    /**
     * All bank keys this user has an active connection for.
     *
     * @return array<string, BankAdapter>
     */
    public function allForUser(int $userId): array
    {
        $rows = UserConnect::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $adapter = $this->getForUser($userId, $row->system_name);
            if ($adapter) {
                $out[$row->system_name] = $adapter;
            }
        }

        return $out;
    }
}
