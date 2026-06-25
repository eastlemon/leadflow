<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\Laravel\Data\Data;

class StatusResult extends Data
{
    public const NEW = 'new';
    public const PROCESSING = 'processing';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const ERROR = 'error';

    public function __construct(
        public string $status,
        public ?string $message = null,
        /** @var array<string, mixed> */
        public array $raw = [],
    ) {
    }
}
