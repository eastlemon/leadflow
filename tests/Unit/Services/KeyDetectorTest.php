<?php

declare(strict_types=1);

use App\Services\KeyDetector;

beforeEach(function (): void {
    $this->detector = app(KeyDetector::class);
});

it('validates a real Russian ИНН (10 digits, юрлицо)', function (): void {
    // 7707083893 — ПАО Сбербанк, общеизвестный валидный ИНН.
    expect($this->detector->validInn('7707083893'))->toBeTrue();
});

it('validates a real Russian ИНН (12 digits, ИП/физлицо)', function (): void {
    // 500100732259 — контрольные цифры валидны.
    expect($this->detector->validInn('500100732259'))->toBeTrue();
});

it('rejects ИНН with wrong checksum', function (): void {
    expect($this->detector->validInn('7707083894'))->toBeFalse();
});

it('rejects non-digit and wrong-length ИНН', function (): void {
    expect($this->detector->validInn(''))->toBeFalse();
    expect($this->detector->validInn('123'))->toBeFalse();
    expect($this->detector->validInn('12345678901234'))->toBeFalse();
    expect($this->detector->validInn('770708389A'))->toBeFalse();
});

it('validates Russian phone numbers starting with 7 or 8', function (): void {
    expect($this->detector->validPhone('+7 (495) 500-50-50'))->toBeTrue();
    expect($this->detector->validPhone('84955005050'))->toBeTrue();
    expect($this->detector->validPhone('79161234567'))->toBeTrue();
});

it('rejects phones with wrong prefix or length', function (): void {
    expect($this->detector->validPhone('+1 555 1234'))->toBeFalse();
    expect($this->detector->validPhone('123'))->toBeFalse();
    expect($this->detector->validPhone('7495'))->toBeFalse();
});

it('validates ОКВЭД in dotted and bare-digit forms', function (): void {
    expect($this->detector->validOkved('62.01'))->toBeTrue();
    expect($this->detector->validOkved('62.01.1'))->toBeTrue();
    expect($this->detector->validOkved('6201'))->toBeTrue();
    expect($this->detector->validOkved('ОКВЭД 62.01'))->toBeTrue();
});

it('rejects ОКВЭД with wrong format', function (): void {
    expect($this->detector->validOkved(''))->toBeFalse();
    expect($this->detector->validOkved('6.01'))->toBeFalse();
    expect($this->detector->validOkved('123'))->toBeFalse();
    expect($this->detector->validOkved('1234567'))->toBeFalse();
});

it('detects column positions for ИНН, phone and ОКВЭД in a mixed row', function (): void {
    $row = [
        'company'  => 'ООО Ромашка',
        'inn'      => '7707083893',
        'name'     => 'Иван Петров',
        'phone'    => '+7 (495) 500-50-50',
        'region'   => 'Москва',
        'okved'    => '62.01',
    ];

    expect($this->detector->detect($row))->toBe([
        'inn'   => 'inn',
        'tel'   => 'phone',
        'okved' => 'okved',
    ]);
});

it('early-exits when all three keys are already found', function (): void {
    $row = [
        'inn'   => '7707083893',
        'tel'   => '+7 495 500-50-50',
        'ok'    => '62.01',
        'extra' => 'noise that should not be visited',
    ];

    expect($this->detector->detect($row))->toBe([
        'inn' => 'inn', 'tel' => 'tel', 'okved' => 'ok',
    ]);
});

it('returns nulls when no keys are found in the row', function (): void {
    expect($this->detector->detect(['foo' => 'bar', 'baz' => 'qux']))
        ->toBe(['inn' => null, 'tel' => null, 'okved' => null]);
});

it('handles numeric-keyed rows (no header)', function (): void {
    $row = [
        0 => 'ООО Ромашка',
        1 => '7707083893',
        2 => '+7 495 500-50-50',
        3 => '62.01',
    ];

    expect($this->detector->detect($row))->toBe(['inn' => 1, 'tel' => 2, 'okved' => 3]);
});

it('treats empty whitelist as "nothing allowed"', function (): void {
    expect($this->detector->isAllowedByWhiteList('7707083893', ''))->toBeFalse();
    expect($this->detector->isAllowedByWhiteList('7707083893', []))->toBeFalse();
});

it('treats empty blacklist as "everything allowed"', function (): void {
    expect($this->detector->isAllowedByBlackList('7707083893', ''))->toBeTrue();
    expect($this->detector->isAllowedByBlackList('7707083893', []))->toBeTrue();
});

it('matches blacklist by exact and prefix', function (): void {
    $list = "7707083893\n1234567890";
    expect($this->detector->isAllowedByBlackList('7707083893', $list))->toBeFalse();
    expect($this->detector->isAllowedByBlackList('7707083894', $list))->toBeTrue();
    expect($this->detector->isAllowedByBlackList('77070838934', $list, prefixMatch: false))->toBeTrue();
});

it('matches whitelist by prefix', function (): void {
    $list = "77070\n50010";
    expect($this->detector->isAllowedByWhiteList('7707083893', $list))->toBeTrue();
    expect($this->detector->isAllowedByWhiteList('7707083894', $list))->toBeTrue();
    expect($this->detector->isAllowedByWhiteList('9999999999', $list))->toBeFalse();
});

it('validateResolved flags fields whose value does not pass validation', function (): void {
    // Column positions resolved by detect() must point at cells that
    // actually pass validation — not just any non-empty cell.
    $row = [
        'inn'   => 'NOT_AN_INN',
        'phone' => '12',          // too short
        'okved' => '6.0',         // wrong format
    ];
    $mapping = $this->detector->detect([]) === ['inn' => null, 'tel' => null, 'okved' => null]
        ? ['inn' => 'inn', 'tel' => 'phone', 'okved' => 'okved']
        : $this->detector->detect($row);

    // detect() with this row would map okved to 'okved' too (it's the
    // only cell with 6.0-like length), so we override the mapping here.
    $errors = $this->detector->validateResolved($row, [
        'inn'   => 'inn',
        'tel'   => 'phone',
        'okved' => 'okved',
    ]);
    expect($errors)->toContain('inn');
    expect($errors)->toContain('tel');
    expect($errors)->toContain('okved');
});

it('validateResolved returns empty when mapping values all validate', function (): void {
    $row = [
        'inn'   => '7707083893',
        'phone' => '+7 495 000-00-00',
        'okved' => '62.01',
    ];
    expect($this->detector->validateResolved($row, [
        'inn' => 'inn', 'tel' => 'phone', 'okved' => 'okved',
    ]))->toBe([]);
});

it('validateResolved skips fields that the mapping points to as null', function (): void {
    expect($this->detector->validateResolved(['x' => 'irrelevant'], [
        'inn' => null, 'tel' => null, 'okved' => null,
    ]))->toBe([]);
});