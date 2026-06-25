<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Readonly DTO describing a single lead entering the system.
 * Validated at the boundary (webhook controller, score/send jobs).
 *
 * Spatie laravel-data gives us:
 *  - constructor promotion + readonly
 *  - automatic snake_case <-> camelCase mapping
 *  - one-line ::from($request) for inbound payloads
 *  - type-safe payloads, easy mocking in tests
 */
#[MapInputName(SnakeCaseMapper::class)]
class LeadData extends Data
{
    public function __construct(
        public string $inn,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $middleName = null,
        public ?string $companyName = null,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $okved = null,
        public ?string $externalId = null,
        /** @var array<string, mixed>|null */
        public ?array $extra = null,
    ) {
    }
}
