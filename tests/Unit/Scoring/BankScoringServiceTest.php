<?php

declare(strict_types=1);

use App\Data\LeadData;
use App\Scoring\BankScoringService;
use App\Scoring\Rules\InnBlacklistRule;
use App\Scoring\Rules\InnWhitelistRule;
use App\Scoring\ScoringConfig;
use App\Scoring\ScoringDecision;
use App\Services\KeyDetector;

it('returns PASS when no rules are configured', function (): void {
    $service = new BankScoringService(rules: []);
    $decision = $service->check(new LeadData(inn: '7707083893'), ScoringConfig::default());

    expect($decision->isPass())->toBeTrue();
});

it('returns the first failing rule and ignores the rest', function (): void {
    $service = new BankScoringService(rules: [
        new InnBlacklistRule(new KeyDetector()),
        new InnWhitelistRule(new KeyDetector()),
    ]);

    $cfg = new ScoringConfig(
        innBlacklist: ['77070'],
        innWhitelist: ['99999'],
    );

    $decision = $service->check(new LeadData(inn: '7707083893'), $cfg);

    // Blacklist fires first, whitelist is never consulted.
    expect($decision->code)->toBe('inn_blacklist');
});

it('falls through to whitelist when blacklist passes', function (): void {
    $service = new BankScoringService(rules: [
        new InnBlacklistRule(new KeyDetector()),
        new InnWhitelistRule(new KeyDetector()),
    ]);

    $cfg = new ScoringConfig(
        innBlacklist: ['12345'],
        innWhitelist: ['77070'],
    );

    $decision = $service->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->isPass())->toBeTrue();
});

it('returns DISABLED when the master switch is off, even with rules', function (): void {
    $service = new BankScoringService(rules: [
        new InnBlacklistRule(new KeyDetector()),
    ]);

    $cfg = new ScoringConfig(
        enabled: false,
        innBlacklist: ['77070'], // would normally reject
    );

    $decision = $service->check(new LeadData(inn: '7707083893'), $cfg);

    expect($decision->status)->toBe(ScoringDecision::DISABLED);
    expect($decision->blocksSend())->toBeTrue();
});
