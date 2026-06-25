<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Configs\AlfaConfig;
use App\Adapters\Configs\PsbConfig;
use App\Adapters\Configs\UralConfig;
use App\Adapters\Configs\VtbConfig;
use RuntimeException;

/**
 * Builds typed AdapterConfig instances from a generic settings array.
 *
 * The legacy code stored per-bank settings as a JSON blob in the
 * `connect.tune` column and let each ServiceCreator parse it ad-hoc.
 * Here we centralize the parsing and let the resulting objects be
 * passed into adapters with full IDE auto-complete.
 */
class ConfigFactory
{
    /**
     * @param  array<string, array<string, mixed>>  $settings
     *         keyed by system_name (alfa, psb, vtb, ural)
     */
    public function fromArray(array $settings): AdapterConfig
    {
        $name = $settings['system_name'] ?? null;
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Adapter config requires a system_name');
        }

        return match ($name) {
            'alfa'  => $this->makeAlfa($settings),
            'psb'   => $this->makePsb($settings),
            'vtb'   => $this->makeVtb($settings),
            'ural'  => $this->makeUral($settings),
            default => throw new RuntimeException("Unknown bank system_name: {$name}"),
        };
    }

    /** @param array<string, mixed> $s */
    private function makeAlfa(array $s): AlfaConfig
    {
        return new AlfaConfig(
            apiUrl: (string) ($s['api_url'] ?? ''),
            apiKey: (string) ($s['api_key'] ?? ''),
            enabled: (bool) ($s['enabled'] ?? true),
            timeoutSeconds: (int) ($s['timeout'] ?? 30),
            retryAttempts: (int) ($s['retry_attempts'] ?? 3),
            retryBackoffSeconds: (int) ($s['retry_backoff'] ?? 5),
        );
    }

    /** @param array<string, mixed> $s */
    private function makePsb(array $s): PsbConfig
    {
        return new PsbConfig(
            apiUrl: (string) ($s['api_url'] ?? ''),
            email: (string) ($s['email'] ?? ''),
            password: (string) ($s['password'] ?? ''),
            enabled: (bool) ($s['enabled'] ?? true),
        );
    }

    /** @param array<string, mixed> $s */
    private function makeVtb(array $s): VtbConfig
    {
        return new VtbConfig(
            apiUrl: (string) ($s['api_url'] ?? ''),
            authUrl: (string) ($s['auth_url'] ?? ''),
            clientId: (string) ($s['client_id'] ?? ''),
            clientSecret: (string) ($s['client_secret'] ?? ''),
            enabled: (bool) ($s['enabled'] ?? true),
        );
    }

    /** @param array<string, mixed> $s */
    private function makeUral(array $s): UralConfig
    {
        return new UralConfig(
            apiUrl: (string) ($s['api_url'] ?? ''),
            apiKey: (string) ($s['api_key'] ?? ''),
            enabled: (bool) ($s['enabled'] ?? true),
        );
    }
}
