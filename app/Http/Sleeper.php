<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Sleep abstraction so tests can verify backoff timing without
 * actually blocking the test runner.
 */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
