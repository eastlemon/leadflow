<?php

declare(strict_types=1);

use App\Jobs\ScoreLeadJob;
use App\Models\Lead;
use App\Models\LeadJob;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookClient\Models\WebhookCall;

it('persists a lead and dispatches score jobs for every bank', function (): void {
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

    Queue::assertPushed(ScoreLeadJob::class, 4); // 4 banks
});
