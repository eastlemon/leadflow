<?php

declare(strict_types=1);

use App\Services\RegionDetector;

beforeEach(function (): void {
    $this->detector = app(RegionDetector::class);
});

it('resolves a region name to its capital', function (): void {
    expect($this->detector->detectCity('московская область'))->toBe('Москва');
    expect($this->detector->detectCity('свердловская область'))->toBe('Екатеринбург');
    expect($this->detector->detectCity('республика татарстан'))->toBe('Казань');
    expect($this->detector->detectCity('приморский край'))->toBe('Владивосток');
});

it('prefers the longer region match (kemerovo-kuzbass wins over kemerovo)', function (): void {
    expect($this->detector->detectCity('кемеровская область - кузбасс'))->toBe('Кемерово');
});

it('extracts a city from a "г." marker', function (): void {
    expect($this->detector->detectCity('г. Тула, ул. Ленина, 1'))->toBe('Тула');
    expect($this->detector->detectCity('город Казань'))->toBe('Казань');
});

it('falls back to the original string when no marker or region matches', function (): void {
    $raw = 'непонятная строка';
    expect($this->detector->detectCity($raw))->toBe($raw);
});

it('returns empty string for empty input', function (): void {
    expect($this->detector->detectCity(''))->toBe('');
});

it('skips street-prefix tokens like ул/пр when reading the city marker', function (): void {
    // "г. УЛ" extracts "УЛ" but isPlausibleCity rejects it (street prefix),
    // then no region match → falls through to the original string.
    expect($this->detector->detectCity('г. УЛ, д. 5'))->toBe('г. УЛ, д. 5');
});

it('honors onlyCapital=true by returning empty on no match', function (): void {
    expect($this->detector->detectCity('что-то странное', onlyCapital: true))->toBe('');
});

it('keeps the preposition in "ростов-на-дону" lowercase', function (): void {
    expect($this->detector->detectCity('ростовская область'))->toBe('Ростов-на-Дону');
});