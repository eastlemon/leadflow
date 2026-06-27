<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Banks\AlfaAdapter;
use App\Adapters\Configs\AlfaConfig;
use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Models\LeadJob;
use App\Models\User;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringConfigFactory;
use App\Services\KeyDetector;
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

    app()->singleton(ScoringConfigFactory::class, function () {
        return new ScoringConfigFactory(new KeyDetector());
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

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(
        app(\App\Adapters\AdapterRegistry::class),
        app(\App\Scoring\ScoringConfigFactory::class),
    );

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

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(
        app(\App\Adapters\AdapterRegistry::class),
        app(\App\Scoring\ScoringConfigFactory::class),
    );

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

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(
        app(\App\Adapters\AdapterRegistry::class),
        app(\App\Scoring\ScoringConfigFactory::class),
    );

    expect(LeadJob::query()->where('lead_id', $lead->id)->count())->toBe(0);
});

it('marks LeadJob as OK with a pre-flight reason when the lead is blacklisted', function (): void {
    // Stub the registry so the Alfa adapter carries a blacklist in its tune.
    app()->forgetInstance(AdapterRegistry::class);
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            public function getForUser(int $userId, string $systemName): ?\App\Adapters\Contracts\BankAdapter
            {
                if ($systemName !== 'alfa') {
                    return null;
                }
                return new AlfaAdapter(new AlfaConfig(
                    apiUrl: 'https://partner.alfabank.ru',
                    apiKey: 'test-key',
                    scoring: new ScoringConfig(innBlacklist: ['77070']),
                ));
            }
        };
    });

    // No Http::fake() — pre-flight must short-circuit before any HTTP call.
    $user = User::factory()->create();
    $lead = Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    (new ScoreLeadJob($lead->id, 'alfa'))->handle(
        app(\App\Adapters\AdapterRegistry::class),
        app(\App\Scoring\ScoringConfigFactory::class),
    );

    $job = LeadJob::query()->where('lead_id', $lead->id)->first();
    expect($job)->not->toBeNull();
    expect($job->status)->toBe(LeadJob::STATUS_OK);
    expect($job->error)->toContain('чёрном списке');
    expect($job->external_id)->toBeNull();
    expect($job->finished_at)->not->toBeNull();
});

it('marks LeadJob as OK with a duplicate reason when the same INN was processed recently', function (): void {
    $user = User::factory()->create();

    // An old lead with the same INN, outside PSB's whitelist by default
    // (PSB has InnWhitelistRule — empty whitelist = pass, so we need a
    // different rule to fire. Use DuplicatePeriodRule by going through
    // PSB with off_days set.)
    \Illuminate\Support\Facades\DB::table('leads')->insert([
        'user_id'    => $user->id,
        'inn'        => '7707083893',
        'source'     => 'test',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    // Swap in a PSB adapter that has a duplicateDays window.
    app()->forgetInstance(AdapterRegistry::class);
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            public function getForUser(int $userId, string $systemName): ?\App\Adapters\Contracts\BankAdapter
            {
                if ($systemName !== 'psb') {
                    return null;
                }
                return new \App\Adapters\Banks\PsbAdapter(new \App\Adapters\Configs\PsbConfig(
                    apiUrl: 'https://api.psb.example',
                    email: 'foo@bar',
                    password: 'secret',
                    scoring: new ScoringConfig(
                        innWhitelist: ['77070'],
                        duplicateDays: 7,
                    ),
                ));
            }
        };
    });

    $lead = Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    (new ScoreLeadJob($lead->id, 'psb'))->handle(
        app(\App\Adapters\AdapterRegistry::class),
        app(\App\Scoring\ScoringConfigFactory::class),
    );

    $job = LeadJob::query()->where('lead_id', $lead->id)->first();
    expect($job)->not->toBeNull();
    expect($job->status)->toBe(LeadJob::STATUS_OK);
    expect($job->error)->toContain('Дубль');
});
