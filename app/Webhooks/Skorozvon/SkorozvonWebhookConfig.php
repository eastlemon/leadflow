<?php

declare(strict_types=1);

namespace App\Webhooks\Skorozvon;

use Spatie\WebhookClient\WebhookConfig;

/**
 * Registers the Скорозвон endpoint with Spatie's WebhookClient.
 *
 * URL: /webhooks/skorozvon
 * Storage: the `webhook_calls` table (published by Spatie)
 * Processor: SkorozzonWebhookProcessor
 */
class SkorozvonWebhookConfig
{
    public static function name(): string
    {
        return 'skorozvon';
    }

    public static function config(): WebhookConfig
    {
        return new WebhookConfig([
            'name'             => self::name(),
            'signing_secret'   => config('leadflow.webhook.skorozvon.signing_secret'),
            'signature_header_name' => 'X-Skorozvon-Signature',
            'webhook_call_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => SkorozzonWebhookProcessor::class,
        ]);
    }
}
