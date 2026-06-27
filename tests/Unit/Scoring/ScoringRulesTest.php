<?php

declare(strict_types=1);

use App\Data\LeadData;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Scoring\ScoringConfig;
use App\Scoring\Rules\DuplicatePeriodRule;
use App\Scoring\Rules\InnBlacklistRule;
use App\Scoring\Rules\InnWhitelistRule;
use App\Scoring\Rules\OkvedBlacklistRule;
use App\Scoring\Rules\SkipExistingRule;
use App\Scoring\ScoringDecision;
use App\Services\KeyDetector;

/**
 * The blacklist/whitelist rules delegate to KeyDetector for prefix
 * matching, so these tests focus on the contract: pass when inactive,
 * reject when matched, pass when not matched.
 */

it('InnBlacklistRule passes when the blacklist is empty', function (): void {
    $rule = new InnBlacklistRule(new KeyDetector());
    $decision = $rule->check(new LeadData(inn: '7707083893'), ScoringConfig::default());

    expect($decision->isPass())->toBeTrue();
});

it('InnBlacklistRule rejects exact match', function (): void {
    $rule = new InnBlacklistRule(new KeyDetector());
    $cfg = new ScoringConfig(innBlacklist: ['7707083893']);
    $decision = $rule->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeFalse();
    expect($decision->code)->toBe('inn_blacklist');
});

it('InnBlacklistRule rejects prefix match', function (): void {
    $rule = new InnBlacklistRule(new KeyDetector());
    $cfg = new ScoringConfig(innBlacklist: ['77070']);
    $decision = $rule->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeFalse();
});

it('InnBlacklistRule passes when INN is not in the list', function (): void {
    $rule = new InnBlacklistRule(new KeyDetector());
    $cfg = new ScoringConfig(innBlacklist: ['111', '222']);
    $decision = $rule->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeTrue();
});

it('OkvedBlacklistRule is a no-op when ОКВЭД is missing', function (): void {
    $rule = new OkvedBlacklistRule(new KeyDetector());
    $cfg = new ScoringConfig(okvedBlacklist: ['62.01']);
    $decision = $rule->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeTrue();
});

it('OkvedBlacklistRule rejects when ОКВЭД matches', function (): void {
    $rule = new OkvedBlacklistRule(new KeyDetector());
    $cfg = new ScoringConfig(okvedBlacklist: ['62.01', '62.02']);
    $decision = $rule->check(new LeadData(inn: '7707083893', okved: '62.01'), $cfg);

    expect($decision->isPass())->toBeFalse();
    expect($decision->code)->toBe('okved_blacklist');
});

it('InnWhitelistRule is a no-op when whitelist is empty', function (): void {
    $rule = new InnWhitelistRule(new KeyDetector());
    $decision = $rule->check(new LeadData(inn: '7707083893'), ScoringConfig::default());

    expect($decision->isPass())->toBeTrue();
});

it('InnWhitelistRule rejects when INN is not in the whitelist', function (): void {
    $rule = new InnWhitelistRule(new KeyDetector());
    $cfg = new ScoringConfig(innWhitelist: ['77070']);
    $decision = $rule->check(new LeadData(inn: '9999999999'), $cfg);

    expect($decision->isPass())->toBeFalse();
    expect($decision->code)->toBe('inn_whitelist');
});

it('InnWhitelistRule passes when INN matches a prefix', function (): void {
    $rule = new InnWhitelistRule(new KeyDetector());
    $cfg = new ScoringConfig(innWhitelist: ['77070']);
    $decision = $rule->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeTrue();
});

it('SkipExistingRule scopes to the same user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Lead::create([
        'user_id' => $userA->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    $rule = new SkipExistingRule();

    $ownConflict = $rule->check(
        new LeadData(inn: '7707083893', userId: $userA->id),
        new ScoringConfig(skipExisting: true),
    );
    expect($ownConflict->isPass())->toBeFalse();
    expect($ownConflict->status)->toBe(ScoringDecision::DUPLICATE);

    $otherUser = $rule->check(
        new LeadData(inn: '7707083893', userId: $userB->id),
        new ScoringConfig(skipExisting: true),
    );
    expect($otherUser->isPass())->toBeTrue();
});

it('SkipExistingRule is inactive when skipExisting is false', function (): void {
    $user = User::factory()->create();
    Lead::create([
        'user_id' => $user->id,
        'inn'     => '7707083893',
        'source'  => 'test',
    ]);

    $rule = new SkipExistingRule();
    $decision = $rule->check(
        new LeadData(inn: '7707083893', userId: $user->id),
        ScoringConfig::default(),
    );

    expect($decision->isPass())->toBeTrue();
});

it('DuplicatePeriodRule rejects when the same INN was processed recently', function (): void {
    $user = User::factory()->create();
    Lead::create([
        'user_id'    => $user->id,
        'inn'        => '7707083893',
        'source'     => 'test',
        'created_at' => now()->subDays(3),
    ]);

    $rule = new DuplicatePeriodRule();
    $decision = $rule->check(
        new LeadData(inn: '7707083893', userId: $user->id),
        new ScoringConfig(duplicateDays: 7),
    );

    expect($decision->isPass())->toBeFalse();
    expect($decision->status)->toBe(ScoringDecision::DUPLICATE);
});

it('DuplicatePeriodRule passes when the last record is older than the window', function (): void {
    $user = User::factory()->create();

    // Bypass Eloquent's auto-stamping of created_at so we can plant a
    // lead that's clearly outside the 7-day window.
    DB::table('leads')->insert([
        'user_id'    => $user->id,
        'inn'        => '7707083893',
        'source'     => 'test',
        'created_at' => now()->subDays(30),
        'updated_at' => now()->subDays(30),
    ]);

    $rule = new DuplicatePeriodRule();
    $decision = $rule->check(
        new LeadData(inn: '7707083893', userId: $user->id),
        new ScoringConfig(duplicateDays: 7),
    );

    expect($decision->isPass())->toBeTrue();
});
