<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Configs\AlfaConfig;
use App\Adapters\Banks\AlfaAdapter;
use App\Data\LeadData;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            public function get(string $systemName, array $settings = []): \App\Adapters\Contracts\BankAdapter
            {
                if ($systemName === 'alfa') {
                    return new AlfaAdapter(new AlfaConfig(
                        apiUrl: 'https://partner.alfabank.ru',
                        apiKey: 'test-key',
                    ));
                }
                return parent::get($systemName, $settings);
            }
        };
    });
});

it('calls the Alfa score endpoint and parses an approval', function (): void {
    Http::fake([
        'partner.alfabank.ru/*' => Http::response([
            'id'       => 'alfa-1',
            'approved' => true,
            'score'    => 87.5,
        ]),
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $result = $registry->get('alfa')->score(new LeadData(inn: '7707083893', phone: '+79991234567'));

    expect($result->success)->toBeTrue();
    expect($result->approved)->toBeTrue();
    expect($result->externalId)->toBe('alfa-1');
    expect($result->score)->toBe(87.5);
});

it('parses a rejection from Alfa', function (): void {
    Http::fake([
        'partner.alfabank.ru/*' => Http::response([
            'approved' => false,
            'reason'   => 'INN is blacklisted',
        ]),
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $result = $registry->get('alfa')->score(new LeadData(inn: '0000000000'));

    expect($result->success)->toBeTrue();
    expect($result->approved)->toBeFalse();
    expect($result->reason)->toBe('INN is blacklisted');
});

it('reports a transport failure', function (): void {
    Http::fake([
        'partner.alfabank.ru/*' => Http::response('boom', 502),
    ]);

    /** @var AdapterRegistry $registry */
    $registry = app(AdapterRegistry::class);
    $result = $registry->get('alfa')->score(new LeadData(inn: '7707083893'));

    expect($result->success)->toBeFalse();
    expect($result->reason)->toContain('502');
});
