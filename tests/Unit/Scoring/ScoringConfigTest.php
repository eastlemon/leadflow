<?php

declare(strict_types=1);

use App\Scoring\ScoringConfig;

it('returns permissive defaults', function (): void {
    $cfg = ScoringConfig::default();

    expect($cfg->innBlacklist)->toBe([]);
    expect($cfg->okvedBlacklist)->toBe([]);
    expect($cfg->innWhitelist)->toBe([]);
    expect($cfg->skipExisting)->toBeFalse();
    expect($cfg->duplicateDays)->toBeNull();
    expect($cfg->enabled)->toBeTrue();
});

it('parses a tune array with string lists', function (): void {
    $cfg = ScoringConfig::fromTune([
        'is_score'        => 'yes',
        'inn_skip_list'   => "12345\n67890, 999",
        'okved_skip_list' => '62.01 62.02',
        'inn_only'        => '770,771',
        'skip_exist'      => 'yes',
        'off_days'        => '30',
    ]);

    expect($cfg->enabled)->toBeTrue();
    expect($cfg->innBlacklist)->toBe(['12345', '67890', '999']);
    expect($cfg->okvedBlacklist)->toBe(['62.01', '62.02']);
    expect($cfg->innWhitelist)->toBe(['770', '771']);
    expect($cfg->skipExisting)->toBeTrue();
    expect($cfg->duplicateDays)->toBe(30);
});

it('treats dash and empty as inactive', function (): void {
    $cfg = ScoringConfig::fromTune([
        'is_score'        => 'no',
        'inn_skip_list'   => '-',
        'okved_skip_list' => '',
        'inn_only'        => '-',
        'off_days'        => '-',
        'skip_exist'      => 'no',
    ]);

    expect($cfg->enabled)->toBeFalse();
    expect($cfg->innBlacklist)->toBe([]);
    expect($cfg->okvedBlacklist)->toBe([]);
    expect($cfg->innWhitelist)->toBe([]);
    expect($cfg->duplicateDays)->toBeNull();
    expect($cfg->skipExisting)->toBeFalse();
});

it('accepts array-shaped lists directly', function (): void {
    $cfg = ScoringConfig::fromTune([
        'inn_skip_list' => ['12345', '67890'],
        'inn_only'      => ['770'],
    ]);

    expect($cfg->innBlacklist)->toBe(['12345', '67890']);
    expect($cfg->innWhitelist)->toBe(['770']);
});

it('normalizes off_days of 0 and negative as null', function (): void {
    expect(ScoringConfig::fromTune(['off_days' => '0'])->duplicateDays)->toBeNull();
    expect(ScoringConfig::fromTune(['off_days' => '-5'])->duplicateDays)->toBeNull();
    expect(ScoringConfig::fromTune(['off_days' => 0])->duplicateDays)->toBeNull();
});

it('interprets boolean-ish strings for is_score and skip_exist', function (): void {
    expect(ScoringConfig::fromTune(['is_score' => '1'])->enabled)->toBeTrue();
    expect(ScoringConfig::fromTune(['is_score' => 'false'])->enabled)->toBeFalse();
    expect(ScoringConfig::fromTune(['is_score' => 'on'])->enabled)->toBeTrue();

    expect(ScoringConfig::fromTune(['skip_exist' => 'yes'])->skipExisting)->toBeTrue();
    expect(ScoringConfig::fromTune(['skip_exist' => 'no'])->skipExisting)->toBeFalse();
});
