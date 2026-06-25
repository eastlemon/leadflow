<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Banks\VtbAdapter;
use App\Adapters\Configs\VtbConfig;
use App\Data\LeadData;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app()->singleton(AdapterRegistry::class, function () {
        return new class extends AdapterRegistry {
            public function __construct() {}
            public function get(string $systemName): \App\Adapters\Contracts\BankAdapter
            {
                if ($systemName !== 'vtb') {
                    return parent::get($systemName);
                }
                return new VtbAdapter(new VtbConfig(
                    apiUrl: 'https://gw.api.vtb.ru',
                    authUrl: 'https://open.api.vtb.ru',
                    clientId: 'cid',
                    clientSecret: 'csec',
                ));
            }
        };
    });
});

it('authenticates with client_credentials and caches the token', function (): void {
    Http::fake([
        'open.api.vtb.ru/passport/oauth2/token' => Http::response([
            'access_token' => 'tok-123',
            'expires_in'   => 3600,
        ]),
        'gw.api.vtb.ru/*' => Http::response([
            'leadId'  => 'vtb-1',
            'decision' => 'approve',
            'score'   => 90.0,
        ]),
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $result = $registry->get('vtb')->score(new LeadData(inn: '7707083893'));

    expect($result->success)->toBeTrue();
    expect($result->approved)->toBeTrue();
    expect($result->externalId)->toBe('vtb-1');
});
