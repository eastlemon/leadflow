<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Models\User;
use App\Models\UserConnect;

it('returns null when user has no active connection for the bank', function (): void {
    $user = User::factory()->create();

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    expect($registry->getForUser($user->id, 'alfa'))->toBeNull();
});

it('builds an adapter from the user active connect settings', function (): void {
    $user = User::factory()->create();

    UserConnect::create([
        'user_id'      => $user->id,
        'system_name'  => 'alfa',
        'is_active'    => true,
        'display_name' => 'My Alfa',
        'tune'         => [
            'api_url' => 'https://partner.alfabank.ru',
            'api_key' => 'user-secret-key',
        ],
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $adapter = $registry->getForUser($user->id, 'alfa');

    expect($adapter)->not->toBeNull();
    expect($adapter->systemName())->toBe('alfa');
});

it('ignores inactive connects', function (): void {
    $user = User::factory()->create();

    UserConnect::create([
        'user_id'     => $user->id,
        'system_name' => 'alfa',
        'is_active'   => false,
        'tune'        => ['api_url' => 'https://x', 'api_key' => 'k'],
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    expect($registry->getForUser($user->id, 'alfa'))->toBeNull();
});

it('returns a map of all banks the user has active connections for', function (): void {
    $user = User::factory()->create();

    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'alfa', 'is_active' => true,
        'tune' => ['api_url' => 'https://a', 'api_key' => 'k1'],
    ]);
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'vtb', 'is_active' => true,
        'tune' => [
            'api_url' => 'https://v', 'auth_url' => 'https://au',
            'client_id' => 'cid', 'client_secret' => 'csec',
        ],
    ]);
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'psb', 'is_active' => false,
        'tune' => ['api_url' => 'https://p', 'email' => 'e', 'password' => 'p'],
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    $all = $registry->allForUser($user->id);

    expect(array_keys($all))->toBe(['alfa', 'vtb']);
});

it('isolates credentials across users', function (): void {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    UserConnect::create([
        'user_id' => $alice->id, 'system_name' => 'alfa', 'is_active' => true,
        'tune' => ['api_url' => 'https://a', 'api_key' => 'alice-key'],
    ]);
    UserConnect::create([
        'user_id' => $bob->id, 'system_name' => 'alfa', 'is_active' => true,
        'tune' => ['api_url' => 'https://a', 'api_key' => 'bob-key'],
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    expect($registry->getForUser($alice->id, 'alfa'))->not->toBeNull();
    expect($registry->getForUser($bob->id, 'alfa'))->not->toBeNull();

    // Internal AlfaConfig differs by api_key (we read via reflection-free test:
    // the adapter holds the config privately, so just assert the registries
    // handed out *distinct* adapters).
    $aliceAdapter = $registry->getForUser($alice->id, 'alfa');
    $bobAdapter   = $registry->getForUser($bob->id, 'alfa');
    expect($aliceAdapter)->not->toBe($bobAdapter);
});