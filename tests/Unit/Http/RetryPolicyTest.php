<?php

declare(strict_types=1);

use App\Http\RetryPolicy;

it('classifies 5xx and 429 as retryable, 4xx as not', function (): void {
    $p = new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1);

    expect($p->isRetryable(500, 1))->toBeTrue();
    expect($p->isRetryable(502, 1))->toBeTrue();
    expect($p->isRetryable(503, 1))->toBeTrue();
    expect($p->isRetryable(429, 1))->toBeTrue();
    expect($p->isRetryable(400, 1))->toBeFalse();
    expect($p->isRetryable(401, 1))->toBeFalse();
    expect($p->isRetryable(403, 1))->toBeFalse();
    expect($p->isRetryable(404, 1))->toBeFalse();
    expect($p->isRetryable(422, 1))->toBeFalse();
});

it('treats null status (transport error) as retryable', function (): void {
    $p = new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1);
    expect($p->isRetryable(null, 1))->toBeTrue();
});

it('stops retrying once max attempts is reached', function (): void {
    $p = new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1);

    expect($p->isRetryable(500, 1))->toBeTrue();
    expect($p->isRetryable(500, 2))->toBeTrue();
    expect($p->isRetryable(500, 3))->toBeFalse();
    expect($p->isRetryable(500, 4))->toBeFalse();
});

it('computes exponential backoff with jitter', function (): void {
    $p = new RetryPolicy(maxAttempts: 5, baseBackoffSeconds: 2, jitterSeconds: 0);

    // attempt N -> base * 2^(N-1)
    expect($p->backoffSeconds(1))->toBe(2);  // before 2nd attempt
    expect($p->backoffSeconds(2))->toBe(4);  // before 3rd attempt
    expect($p->backoffSeconds(3))->toBe(8);
    expect($p->backoffSeconds(4))->toBe(16);
});

it('caps the delay at non-negative', function (): void {
    $p = new RetryPolicy(maxAttempts: 1, baseBackoffSeconds: 0);
    expect($p->backoffSeconds(1))->toBe(0);
});

it('exposes a no-retry policy', function (): void {
    $p = RetryPolicy::noRetry();

    expect($p->maxAttempts)->toBe(1);
    expect($p->isRetryable(500, 1))->toBeFalse();
    expect($p->isRetryable(null, 1))->toBeFalse();
});

it('builds a policy from an AdapterConfig', function (): void {
    $config = new \App\Adapters\Configs\AlfaConfig(
        apiUrl: 'https://x',
        apiKey: 'k',
        retryAttempts: 4,
        retryBackoffSeconds: 3,
    );
    $p = RetryPolicy::fromConfig($config);

    expect($p->maxAttempts)->toBe(4);
    expect($p->baseBackoffSeconds)->toBe(3);
    expect($p->jitterSeconds)->toBe(2); // ceil(3/2)
});
