<?php

declare(strict_types=1);

use App\Http\BankHttpClient;
use App\Http\BankHttpResponse;
use App\Http\Events\BankRequestFailed;
use App\Http\RetryPolicy;
use App\Http\Sleeper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * BankHttpClient tests use a fake Http::factory so we can script
 * the responses per attempt, plus a fake Sleeper to verify backoff
 * timing without blocking the test runner.
 */

function makeBankHttpClient(?Sleeper $sleeper = null): BankHttpClient
{
    return new BankHttpClient(
        events: app(\Illuminate\Contracts\Events\Dispatcher::class),
        sleeper: $sleeper ?? new class implements Sleeper {
            public array $delays = [];
            public function sleep(int $seconds): void
            {
                $this->delays[] = $seconds;
            }
        },
    );
}

function tapSleeper(BankHttpClient $client): object
{
    $r = new ReflectionClass($client);
    $p = $r->getProperty('sleeper');
    $p->setAccessible(true);

    return $p->getValue($client);
}

it('returns a successful response on the first try without sleeping', function (): void {
    Http::fake([
        'example.com/*' => Http::response(['ok' => true], 200),
    ]);

    $client = makeBankHttpClient();
    $sleeper = tapSleeper($client);

    $response = $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 5),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    expect($response->successful)->toBeTrue();
    expect($response->status)->toBe(200);
    expect($response->attempts)->toBe(1);
    expect($sleeper->delays)->toBe([]);
});

it('retries on 5xx and gives up after maxAttempts', function (): void {
    Http::fake([
        'example.com/*' => Http::response('boom', 502),
    ]);

    Event::fake([BankRequestFailed::class]);

    $client = makeBankHttpClient();
    $sleeper = tapSleeper($client);

    $response = $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    expect($response->successful)->toBeFalse();
    expect($response->status)->toBe(502);
    expect($response->attempts)->toBe(3);
    expect($response->error)->toContain('Bad Gateway');
    expect($sleeper->delays)->toBe([1, 2]); // 2 retries, exponential 1s, 2s

    Event::assertDispatched(BankRequestFailed::class, function (BankRequestFailed $e) {
        return $e->attempts === 3
            && $e->lastStatus === 502
            && $e->systemName === 'alfa';
    });
});

it('retries on 429', function (): void {
    Http::fake([
        'example.com/*' => Http::sequence()
            ->push('rate-limited', 429)
            ->push(['ok' => true], 200),
    ]);

    $client = makeBankHttpClient();

    $response = $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    expect($response->successful)->toBeTrue();
    expect($response->attempts)->toBe(2);
});

it('does NOT retry on 4xx other than 429', function (): void {
    Http::fake([
        'example.com/*' => Http::response('forbidden', 403),
    ]);

    Event::fake([BankRequestFailed::class]);

    $client = makeBankHttpClient();
    $sleeper = tapSleeper($client);

    $response = $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    expect($response->status)->toBe(403);
    expect($response->attempts)->toBe(1);
    expect($sleeper->delays)->toBe([]);

    // No event — the request did not exhaust retries, it just got a
    // permanent error. Adapter's job is to report it; alert is for
    // "we tried, the bank is down"-shaped failures.
    Event::assertNotDispatched(BankRequestFailed::class);
});

it('retries on ConnectionException and reports a transport error after exhaustion', function (): void {
    // Http::fake's connectionException: pass a closure that throws.
    Http::fake([
        'example.com/*' => function () {
            throw new ConnectionException('connection refused');
        },
    ]);

    Event::fake([BankRequestFailed::class]);

    $client = makeBankHttpClient();

    $response = $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 2, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    expect($response->successful)->toBeFalse();
    expect($response->isTransportError())->toBeTrue();
    expect($response->error)->toContain('connection refused');
    expect($response->attempts)->toBe(2);

    Event::assertDispatched(BankRequestFailed::class, function (BankRequestFailed $e) {
        return $e->lastStatus === null
            && $e->lastError !== null
            && str_contains((string) $e->lastError, 'connection refused');
    });
});

it('succeeds when a retry recovers from a transient 5xx', function (): void {
    Http::fake([
        'example.com/*' => Http::sequence()
            ->push('boom', 500)
            ->push('boom', 503)
            ->push(['id' => 'abc'], 200),
    ]);

    $client = makeBankHttpClient();

    $response = $client->withRetry(
        method: 'POST',
        url: 'https://example.com/leads',
        systemName: 'psb',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::post('https://example.com/leads', []),
    );

    expect($response->successful)->toBeTrue();
    expect($response->attempts)->toBe(3);
    expect($response->json('id'))->toBe('abc');
});

it('dispatches no event on a clean success', function (): void {
    Http::fake([
        'example.com/*' => Http::response(['ok' => true]),
    ]);

    Event::fake([BankRequestFailed::class]);

    $client = makeBankHttpClient();
    $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );

    Event::assertNotDispatched(BankRequestFailed::class);
});

it('rebuilds the action on every attempt (auth refresh pattern)', function (): void {
    // The closure must be invoked once per attempt — not cached.
    Http::fake([
        'example.com/*' => Http::response(['ok' => true]),
    ]);

    $client = makeBankHttpClient();
    $invocations = 0;

    $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'psb',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: function () use (&$invocations): Response {
            $invocations++;
            // First call: throw (simulate expired token, retried).
            if ($invocations === 1) {
                throw new ConnectionException('expired token');
            }
            return Http::get('https://example.com/foo');
        },
    );

    expect($invocations)->toBe(2);
});

it('rethrows non-ConnectionException throwables (programmer errors)', function (): void {
    Http::fake([
        'example.com/*' => function () {
            throw new \RuntimeException('programmer bug');
        },
    ]);

    $client = makeBankHttpClient();

    $client->withRetry(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        policy: new RetryPolicy(maxAttempts: 3, baseBackoffSeconds: 1),
        action: fn (): Response => Http::get('https://example.com/foo'),
    );
})->throws(\RuntimeException::class, 'programmer bug');
