<?php

declare(strict_types=1);

use App\Data\LeadData;

it('constructs a LeadData with defaults', function (): void {
    $lead = new LeadData(inn: '7707083893');

    expect($lead->inn)->toBe('7707083893');
    expect($lead->phone)->toBeNull();
    expect($lead->extra)->toBeNull();
});

it('maps snake_case input via Spatie data', function (): void {
    $lead = LeadData::from([
        'inn'         => '7707083893',
        'first_name'  => 'Иван',
        'last_name'   => 'Петров',
        'company_name' => 'ООО Ромашка',
    ]);

    expect($lead->firstName)->toBe('Иван');
    expect($lead->lastName)->toBe('Петров');
    expect($lead->companyName)->toBe('ООО Ромашка');
});
