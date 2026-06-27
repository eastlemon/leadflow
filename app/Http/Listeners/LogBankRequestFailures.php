<?php

declare(strict_types=1);

namespace App\Http\Listeners;

use App\Http\Events\BankRequestFailed;
use Illuminate\Support\Facades\Log;

/**
 * Default listener that pipes every bank request failure to the
 * application log at `critical` level.
 *
 * The legacy TellFax code silently swallowed transport errors, so
 * a misconfigured adapter or a flapping bank API could go unnoticed
 * for days. The minimum bar here is "loud in the log" — we can add
 * Slack/email listeners later without changing the dispatch site.
 */
final class LogBankRequestFailures
{
    public function handle(BankRequestFailed $event): void
    {
        Log::critical('Bank HTTP request failed after all retries', [
            'method'         => $event->method,
            'url'            => $event->url,
            'system_name'    => $event->systemName,
            'attempts'       => $event->attempts,
            'last_status'    => $event->lastStatus,
            'last_error'     => $event->lastError,
            'elapsed_secs'   => round($event->elapsedSeconds, 3),
            'payload_keys'   => array_keys($event->payload),
        ]);
    }
}
