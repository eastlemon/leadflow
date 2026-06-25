<?php

declare(strict_types=1);

use App\Adapters\ConfigFactory;
use App\Adapters\Configs\AlfaConfig;
use App\Adapters\Configs\PsbConfig;
use App\Adapters\Configs\UralConfig;
use App\Adapters\Configs\VtbConfig;

it('builds an AlfaConfig from array', function (): void {
    $cfg = (new ConfigFactory)->fromArray([
        'system_name' => 'alfa',
        'api_url'     => 'https://partner.alfabank.ru',
        'api_key'     => 'abc',
        'enabled'     => true,
    ]);

    expect($cfg)->toBeInstanceOf(AlfaConfig::class);
    expect($cfg->apiKey)->toBe('abc');
    expect($cfg->apiUrl)->toBe('https://partner.alfabank.ru');
    expect($cfg->isEnabled())->toBeTrue();
    expect($cfg->systemName)->toBe('alfa');
    expect($cfg->displayName)->toBe('Альфа-Банк');
});

it('builds a PsbConfig with email/password', function (): void {
    $cfg = (new ConfigFactory)->fromArray([
        'system_name' => 'psb',
        'api_url'     => 'https://api.lk.psb.services',
        'email'       => 'foo@bar',
        'password'    => 'secret',
    ]);

    expect($cfg)->toBeInstanceOf(PsbConfig::class);
    expect($cfg->email)->toBe('foo@bar');
    expect($cfg->password)->toBe('secret');
});

it('builds a VtbConfig with OAuth credentials', function (): void {
    $cfg = (new ConfigFactory)->fromArray([
        'system_name'    => 'vtb',
        'api_url'        => 'https://gw.api.vtb.ru',
        'auth_url'       => 'https://open.api.vtb.ru',
        'client_id'      => 'cid',
        'client_secret'  => 'csec',
    ]);

    expect($cfg)->toBeInstanceOf(VtbConfig::class);
    expect($cfg->clientId)->toBe('cid');
    expect($cfg->clientSecret)->toBe('csec');
});

it('builds an UralConfig with api key', function (): void {
    $cfg = (new ConfigFactory)->fromArray([
        'system_name' => 'ural',
        'api_url'     => 'https://ural.example',
        'api_key'     => 'ukey',
    ]);

    expect($cfg)->toBeInstanceOf(UralConfig::class);
    expect($cfg->apiKey)->toBe('ukey');
});

it('rejects unknown system_name', function (): void {
    (new ConfigFactory)->fromArray(['system_name' => 'nope']);
})->throws(RuntimeException::class);

it('rejects missing system_name', function (): void {
    (new ConfigFactory)->fromArray([]);
})->throws(RuntimeException::class);
