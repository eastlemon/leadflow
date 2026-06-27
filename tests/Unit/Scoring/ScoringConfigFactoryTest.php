<?php

declare(strict_types=1);

use App\Scoring\ScoringConfigFactory;
use App\Scoring\Rules\DuplicatePeriodRule;
use App\Scoring\Rules\InnBlacklistRule;
use App\Scoring\Rules\InnWhitelistRule;
use App\Scoring\Rules\OkvedBlacklistRule;
use App\Scoring\Rules\SkipExistingRule;
use App\Services\KeyDetector;

it('builds the Alfa pipeline: blacklist + okved + skip_existing', function (): void {
    $factory = new ScoringConfigFactory(new KeyDetector());
    $result = $factory->forBank('alfa', []);

    expect(array_map(fn ($r) => $r::class, $result['rules']))->toBe([
        InnBlacklistRule::class,
        OkvedBlacklistRule::class,
        SkipExistingRule::class,
    ]);
});

it('builds the PSB pipeline: blacklist + whitelist + skip_existing + duplicate_period', function (): void {
    $factory = new ScoringConfigFactory(new KeyDetector());
    $result = $factory->forBank('psb', []);

    expect(array_map(fn ($r) => $r::class, $result['rules']))->toBe([
        InnBlacklistRule::class,
        InnWhitelistRule::class,
        SkipExistingRule::class,
        DuplicatePeriodRule::class,
    ]);
});

it('builds the VTB pipeline: blacklist + skip_existing + duplicate_period', function (): void {
    $factory = new ScoringConfigFactory(new KeyDetector());
    $result = $factory->forBank('vtb', []);

    expect(array_map(fn ($r) => $r::class, $result['rules']))->toBe([
        InnBlacklistRule::class,
        SkipExistingRule::class,
        DuplicatePeriodRule::class,
    ]);
});

it('builds the Ural pipeline: whitelist + skip_existing + duplicate_period', function (): void {
    $factory = new ScoringConfigFactory(new KeyDetector());
    $result = $factory->forBank('ural', []);

    expect(array_map(fn ($r) => $r::class, $result['rules']))->toBe([
        InnWhitelistRule::class,
        SkipExistingRule::class,
        DuplicatePeriodRule::class,
    ]);
});

it('throws on unknown system_name', function (): void {
    (new ScoringConfigFactory(new KeyDetector()))->forBank('bogus', []);
})->throws(InvalidArgumentException::class);

it('passes the tune through to the typed config', function (): void {
    $factory = new ScoringConfigFactory(new KeyDetector());
    $result = $factory->forBank('alfa', [
        'is_score'      => 'no',
        'inn_skip_list' => '111,222',
    ]);

    expect($result['config']->enabled)->toBeFalse();
    expect($result['config']->innBlacklist)->toBe(['111', '222']);
});
