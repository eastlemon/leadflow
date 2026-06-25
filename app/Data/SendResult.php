<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class SendResult extends Data
{
    public function __construct(
        public bool $success,
        public ?string $externalId = null,
        public ?string $message = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {
    }

    public static function ok(string $externalId, ?string $message = null): self
    {
        return new self(success: true, externalId: $externalId, message: $message);
    }

    public static function failed(string $message): self
    {
        return new self(success: false, message: $message);
    }
}
