<?php

declare(strict_types=1);

use App\Http\Events\BankRequestFailed;
use App\Http\Listeners\LogBankRequestFailures;
use Illuminate\Support\Facades\Log;

it('logs every BankRequestFailed event at critical level with full context', function (): void {
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return $message === 'Bank HTTP request failed after all retries'
                && $context['method'] === 'POST'
                && $context['url'] === 'https://example.com/leads'
                && $context['system_name'] === 'psb'
                && $context['attempts'] === 3
                && $context['last_status'] === 502
                && $context['last_error'] === 'Bad Gateway'
                && $context['payload_keys'] === ['inn', 'phone'];
        });

    $listener = new LogBankRequestFailures();
    $listener->handle(new BankRequestFailed(
        method: 'POST',
        url: 'https://example.com/leads',
        systemName: 'psb',
        attempts: 3,
        lastStatus: 502,
        lastError: 'Bad Gateway',
        elapsedSeconds: 12.345,
        payload: ['inn' => '7707083893', 'phone' => '+7...'],
    ));
});

it('handles transport errors (no status) without choking', function (): void {
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message, array $context) {
            return $context['last_status'] === null
                && str_contains($context['last_error'], 'connection refused');
        });

    $listener = new LogBankRequestFailures();
    $listener->handle(new BankRequestFailed(
        method: 'GET',
        url: 'https://example.com/foo',
        systemName: 'alfa',
        attempts: 2,
        lastStatus: null,
        lastError: 'connection refused',
        elapsedSeconds: 1.2,
    ));
});
