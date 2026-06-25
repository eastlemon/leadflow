<?php

declare(strict_types=1);

use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserConnect;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookClient\Models\WebhookCall;

it('persists a lead and dispatches score jobs for every bank (no user attached)', function (): void {
    Queue::fake();

    $payload = [
        'inn'        => '7707083893',
        'phone'      => '+79991234567',
        'email'      => 'test@example.com',
        'first_name' => 'Иван',
        'last_name'  => 'Петров',
        'company'    => 'ООО Ромашка',
        'city'       => 'Москва',
    ];

    $call = WebhookCall::create([
        'name'        => 'skorozvon',
        'url'         => 'https://imsg.rigroll.ru/webhooks/skorozvon',
        'headers'     => ['X-Skorozvon-Signature' => 'fake'],
        'payload'     => $payload,
    ]);

    (new \App\Webhooks\Skorozvon\SkorozzonWebhookProcessor($call))->handle();

    expect(Lead::count())->toBe(1);
    expect(Lead::first()->inn)->toBe('7707083893');
    expect(Lead::first()->first_name)->toBe('Иван');
    expect(Lead::first()->user_id)->toBeNull();

    Queue::assertPushed(ScoreLeadJob::class, 4); // 4 banks as fallback
});

it('resolves user_id from user_email payload and fans out only to that user active banks', function (): void {
    Queue::fake();

    $user = User::factory()->create(['email' => 'alice@example.com']);
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'alfa', 'is_active' => true,
        'tune' => ['api_url' => 'https://a', 'api_key' => 'k1'],
    ]);
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'vtb', 'is_active' => true,
        'tune' => [
            'api_url' => 'https://v', 'auth_url' => 'https://au',
            'client_id' => 'cid', 'client_secret' => 'csec',
        ],
    ]);
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'psb', 'is_active' => false,
        'tune' => ['api_url' => 'https://p', 'email' => 'e', 'password' => 'p'],
    ]);

    $payload = [
        'inn'        => '7707083893',
        'user_email' => 'alice@example.com',
    ];

    $call = WebhookCall::create([
        'name'    => 'skorozzon',
        'url'     => 'https://imsg.rigroll.ru/webhooks/skorozvon',
        'headers' => ['X-Skorozvon-Signature' => 'fake'],
        'payload' => $payload,
    ]);

    (new \App\Webhooks\Skorozvon\SkorozzonWebhookProcessor($call))->handle();

    $lead = Lead::first();
    expect($lead->user_id)->toBe($user->id);

    // 2 active banks: alfa + vtb. psb is active=false, ural has no row.
    Queue::assertPushed(ScoreLeadJob::class, 2);
    Queue::assertPushed(ScoreLeadJob::class, fn ($job) => $job->systemName === 'alfa');
    Queue::assertPushed(ScoreLeadJob::class, fn ($job) => $job->systemName === 'vtb');
});