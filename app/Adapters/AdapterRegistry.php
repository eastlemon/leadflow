<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\BankAdapter;
use App\Models\Pipeline;
use App\Models\PipelineReceiver;
use App\Scoring\ScoringConfigFactory;
use RuntimeException;

/**
 * Explicit, hand-curated map from system_name to adapter class.
 *
 * Multi-pipeline aware: `getForPipeline()` and `allForPipeline()`
 * build adapters from a PipelineReceiver's `tune` JSON, so the same
 * bank can have different settings under different pipelines.
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
     * Build an adapter from an arbitrary settings array.
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

        return app($class, ['config' => $config]);
    }

    /**
     * Build an adapter for a specific pipeline receiver.
     *
     * The receiver's `tune` JSON provides bank-specific settings
     * (API URL, credentials, scoring params, etc.) for THIS pipeline.
     */
    public function getForReceiver(PipelineReceiver $receiver): BankAdapter
    {
        return $this->get($receiver->system_name, array_merge(
            (array) $receiver->tune,
            ['system_name' => $receiver->system_name],
        ));
    }

    /**
     * All active adapters for a pipeline (fan-out target list).
     *
     * @return array<string, BankAdapter>
     */
    public function allForPipeline(Pipeline $pipeline): array
    {
        $out = [];
        foreach ($pipeline->activeReceivers as $receiver) {
            if ($this->has($receiver->system_name)) {
                $out[$receiver->system_name] = $this->getForReceiver($receiver);
            }
        }

        return $out;
    }

    /**
     * System names of all active receivers in a pipeline.
     *
     * @return string[]
     */
    public function pipelineReceiverNames(Pipeline $pipeline): array
    {
        return $pipeline->activeReceivers()
            ->pluck('system_name')
            ->values()
            ->all();
    }

    /**
     * Build an adapter with credentials pulled from a specific user's
     * `user_connects` row. Legacy method — prefer getForPipeline().
     *
     * @deprecated Use allForPipeline() instead.
     */
    public function getForUser(int $userId, string $systemName): ?BankAdapter
    {
        $row = \App\Models\UserConnect::query()
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
     * Legacy method — prefer allForPipeline().
     *
     * @deprecated Use allForPipeline() instead.
     *
     * @return array<string, BankAdapter>
     */
    public function allForUser(int $userId): array
    {
        $rows = \App\Models\UserConnect::query()
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