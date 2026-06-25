<?php

declare(strict_types=1);

/**
 * Per-bank adapter settings.
 *
 * Replace the placeholders with real values; in production these
 * come from the encrypted `connect` table — see the `Connect` model
 * and the `SyncConnectSettings` command.
 */
return [

    'banks' => [

        'alfa' => [
            'system_name'    => 'alfa',
            'enabled'        => env('ALFA_ENABLED', true),
            'api_url'        => env('ALFA_API_URL', 'https://partner.alfabank.ru'),
            'api_key'        => env('ALFA_API_KEY', ''),
            'timeout'        => env('ALFA_TIMEOUT', 30),
            'retry_attempts' => 3,
            'retry_backoff'  => 5,
        ],

        'psb' => [
            'system_name' => 'psb',
            'enabled'     => env('PSB_ENABLED', true),
            'api_url'     => env('PSB_API_URL', 'https://api.lk.psb.services'),
            'email'       => env('PSB_EMAIL', ''),
            'password'    => env('PSB_PASSWORD', ''),
            'timeout'     => env('PSB_TIMEOUT', 30),
        ],

        'vtb' => [
            'system_name'    => 'vtb',
            'enabled'        => env('VTB_ENABLED', true),
            'api_url'        => env('VTB_API_URL', 'https://gw.api.vtb.ru:443/openapi/smb/lecs/lead-impers/v1'),
            'auth_url'       => env('VTB_AUTH_URL', 'https://open.api.vtb.ru:443'),
            'client_id'      => env('VTB_CLIENT_ID', ''),
            'client_secret'  => env('VTB_CLIENT_SECRET', ''),
            'timeout'        => env('VTB_TIMEOUT', 30),
        ],

        'ural' => [
            'system_name' => 'ural',
            'enabled'     => env('URAL_ENABLED', true),
            'api_url'     => env('URAL_API_URL', ''),
            'api_key'     => env('URAL_API_KEY', ''),
            'timeout'     => env('URAL_TIMEOUT', 30),
        ],

    ],

    'webhook' => [
        'skorozvon' => [
            'enabled'  => env('SKOROZVON_WEBHOOK_ENABLED', true),
            'signing_secret' => env('SKOROZVON_WEBHOOK_SECRET', ''),
        ],
    ],

];
