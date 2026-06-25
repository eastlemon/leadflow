<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Banks\AlfaAdapter;
use App\Adapters\Banks\PsbAdapter;
use App\Adapters\Banks\UralAdapter;
use App\Adapters\Banks\VtbAdapter;
use App\Adapters\ConfigFactory;

it('knows all four banks by system_name', function (): void {
    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    expect($registry->has('alfa'))->toBeTrue();
    expect($registry->has('psb'))->toBeTrue();
    expect($registry->has('vtb'))->toBeTrue();
    expect($registry->has('ural'))->toBeTrue();
    expect($registry->has('unknown'))->toBeFalse();
});

it('returns the correct adapter class per bank', function (): void {
    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);

    expect($registry->get('alfa'))->toBeInstanceOf(AlfaAdapter::class);
    expect($registry->get('psb'))->toBeInstanceOf(PsbAdapter::class);
    expect($registry->get('vtb'))->toBeInstanceOf(VtbAdapter::class);
    expect($registry->get('ural'))->toBeInstanceOf(UralAdapter::class);
});

it('throws on unknown bank', function (): void {
    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $registry->get('bogus');
})->throws(RuntimeException::class);

it('passes typed config to the adapter', function (): void {
    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $alfa = $registry->get('alfa');
    expect($alfa->systemName())->toBe('alfa');
    expect($alfa->displayName())->toBe('Альфа-Банк');
});
