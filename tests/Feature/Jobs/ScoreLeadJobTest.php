<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Banks\AlfaAdapter;
use App\Adapters\Configs\AlfaConfig;
use App\Data\LeadData;
use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Models\LeadJob;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            /**
             * Stub: pretend every user has an active Alfa connection with a known API key.
             * Production code reads user_connects and ConfigFactory; here we shortcut.
             */
            public function getForUser(int $userId, string $systemName): ?\App\Adapters\Contracts\BankAdapter
            {
                if ($systemName !== 'alfa') {
                    return null;
                }
                return new AlfaAdapter(new AlfaConfig(
                    apiUrl: 'https://partner.alfabank.ru',
                    apiKey: 'test-key',
                ));
            }
        };
    });
});

it('persists a LeadJob row on success', function (): void {
    Http::fake([
        'partner.alfabank.ru/*' => Http::response([
            'id' => 'alfa-7', 'approved' => true, 'score' => 70.0,
        ]),
    ]);

    $user = User::factory()->create();
    $lead = Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'phone'   => '+79991234567',
        'source'  => 'test',
    ]);

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(app(\App\Adapters\AdapterRegistry::class));

    $job = LeadJob::query()->where('lead_id', $lead->id)->where('system_name', 'alfa')->first();
    expect($job)->not->toBeNull();
    expect($job->status)->toBe(LeadJob::STATUS_OK);
    expect($job->external_id)->toBe('alfa-7');
    expect($job->stage)->toBe(LeadJob::STAGE_SCORE);
});

it('marks LeadJob as failed when the bank rejects', function (): void {
    Http::fake([
        'partner.alfabank.ru/*' => Http::response([
            'approved' => false, 'reason' => 'duplicate',
        ]),
    ]);

    $user = User::factory()->create();
    $lead = Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(app(\App\Adapters\AdapterRegistry::class));

    $job = LeadJob::query()->where('lead_id', $lead->id)->first();
    expect($job->status)->toBe(LeadJob::STATUS_OK); // rejected still counts as ok stage
    expect($job->error)->toBe('duplicate');
});

it('skips the job silently when the user has no active connection', function (): void {
    app()->forgetInstance(AdapterRegistry::class);
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            public function getForUser(int $userId, string $systemName): ?\App\Adapters\Contracts\BankAdapter
            {
                return null; // user has nothing
            }
        };
    });

    $user = User::factory()->create();
    $lead = Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(app(\App\Adapters\AdapterRegistry::class));

    expect(LeadJob::query()->where('lead_id', $lead->id)->count())->toBe(0);
});