<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Events\BankRequestFailed;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Retry-aware HTTP client for bank API calls.
 *
 * Replaces the silent `Http::post()`-and-`@`-suppress pattern that
 * the legacy TellFax adapters used. The contract:
 *
 *   1. The adapter hands us a closure that performs one attempt
 *      (auth + headers + request). We re-invoke the closure on
 *      every retry, so PSB/VTB can re-fetch an expired token.
 *
 *   2. Transient failures (5xx, 429, ConnectionException) are
 *      retried with exponential backoff, up to the policy's
 *      maxAttempts. Permanent failures (4xx other than 429) are
 *      returned as-is, no retry.
 *
 *   3. We dispatch `BankRequestFailed` when ALL attempts were
 *      exhausted. The default listener logs at `critical` —
 *      adding Slack/email later is a new listener away.
 *
 *   4. The client never throws for HTTP-level outcomes. Adapters
 *      get a structured `BankHttpResponse` they can branch on.
 */
final class BankHttpClient
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly Sleeper $sleeper,
    ) {
    }

    /**
     * Run `$action` under the given retry policy.
     *
     * $action() must return an `Illuminate\Http\Client\Response`
     * (or throw `ConnectionException` on a transport-level failure).
     * It will be re-invoked on each retry.
     */
    public function withRetry(
        string $method,
        string $url,
        string $systemName,
        RetryPolicy $policy,
        Closure $action,
        array $payload = [],
    ): BankHttpResponse {
        $attempts = 0;
        $lastException = null;
        $lastResponse = null;
        $shouldDispatch = false;
        $start = hrtime(true);

        while ($attempts < $policy->maxAttempts) {
            $attempts++;

            try {
                $response = $action();

                if (! $response instanceof Response) {
                    $lastException = new ConnectionException(
                        'Bank HTTP action returned a non-Response value',
                    );
                    $shouldDispatch = true;
                    if (! $policy->isRetryable(null, $attempts)) {
                        break;
                    }
                } else {
                    if ($response->successful()) {
                        return BankHttpResponse::ok(
                            $response,
                            $attempts,
                            $this->elapsed($start),
                        );
                    }

                    $lastResponse = $response;

                    if (! $policy->isRetryable($response->status(), $attempts)) {
                        // 4xx (other than 429): permanent client-side
                        // problem. No retry, no alert — adapter can
                        // surface the message to the operator.
                        if ($response->status() < 500 && $response->status() !== 429) {
                            return BankHttpResponse::httpError(
                                $response,
                                $attempts,
                                $this->elapsed($start),
                            );
                        }

                        // 5xx / 429 with budget exhausted — silent
                        // failure here is the bug we are fixing. Alert.
                        $shouldDispatch = true;
                        break;
                    }
                }
            } catch (ConnectionException $e) {
                $lastException = $e;
                $shouldDispatch = true;
                if (! $policy->isRetryable(null, $attempts)) {
                    break;
                }
            } catch (Throwable $e) {
                // Programmer / config error — re-throw immediately.
                // The retry policy is for transient network / bank
                // problems, not for our own bugs.
                throw $e;
            }

            if ($attempts < $policy->maxAttempts) {
                $this->sleeper->sleep($policy->backoffSeconds($attempts));
            }
        }

        if ($shouldDispatch) {
            $this->events->dispatch(new BankRequestFailed(
                method: $method,
                url: $url,
                systemName: $systemName,
                attempts: $attempts,
                lastStatus: $lastResponse?->status(),
                lastError: $lastException?->getMessage() ?? $lastResponse?->reason(),
                elapsedSeconds: $this->elapsed($start),
                payload: $payload,
            ));
        }

        if ($lastException !== null) {
            return BankHttpResponse::transportError(
                $lastException->getMessage(),
                $attempts,
                $this->elapsed($start),
            );
        }

        return BankHttpResponse::httpError(
            $lastResponse,
            $attempts,
            $this->elapsed($start),
        );
    }

    private function elapsed(int $startNs): float
    {
        return (hrtime(true) - $startNs) / 1_000_000_000;
    }
}
