<?php

declare(strict_types=1);

use App\Adapters\AdapterRegistry;
use App\Adapters\Banks\AlfaAdapter;
use App\Adapters\Configs\AlfaConfig;
use App\Jobs\LoaderJob;
use App\Models\File;
use App\Models\Lead;
use App\Models\LeadJob;
use App\Models\User;
use App\Models\UserConnect;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds a real .xlsx in tmp storage and pushes it through the full pipeline.
 * Verifies: File row created, leads fan-out to user's active banks only,
 * LoaderJob picks up the saved file and emits the right Lead rows.
 */

beforeEach(function (): void {
    Storage::fake('local');

    // Stub registry: user only has Alfa active.
    app()->singleton(AdapterRegistry::class, function () {
        return new class(app(\App\Adapters\ConfigFactory::class)) extends AdapterRegistry {
            public function getForUser(int $userId, string $systemName): ?\App\Adapters\Contracts\BankAdapter
            {
                if ($systemName !== 'alfa') {
                    return null;
                }
                return app(\App\Adapters\Banks\AlfaAdapter::class, ['config' => new AlfaConfig(
                    apiUrl: 'https://partner.alfabank.ru',
                    apiKey: 'test-key',
                )]);
            }
        };
    });
});

function writeFixtureXlsx(string $absolutePath, array $rows): void
{
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    foreach ($rows as $r => $cells) {
        foreach ($cells as $c => $val) {
            $col = chr(64 + $c + 1); $sheet->setCellValue($col . ($r + 1), $val);
        }
    }
    (new Xlsx($ss))->save($absolutePath);
    $ss->disconnectWorksheets();
}

it('creates one Lead per data row and fans out per active bank', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    UserConnect::create([
        'user_id' => $user->id, 'system_name' => 'alfa', 'is_active' => true,
        'tune' => ['api_url' => 'https://partner.alfabank.ru', 'api_key' => 'k'],
    ]);

    $fixture = sys_get_temp_dir().'/leadflow-fixture-'.uniqid().'.xlsx';
    writeFixtureXlsx($fixture, [
        ['company', 'inn', 'phone', 'оквэд'],           // header
        ['ООО Ромашка', '7707083893', '+7 495 000-00-00', '62.01'],
        ['ИП Иванов', '500100732259', '8 916 123-45-67', '62.01.1'],
        ['empty row, should be skipped', '', '', ''],
    ]);

    $file = File::create([
        'user_id'           => $user->id,
        'name'              => 'leads.xlsx',
        'uniq_name'         => 'fixture',
        'target'            => "uploads/u_{$user->id}",
        'ext'               => 'xlsx',
        'is_new'            => true,
        'detected_columns'  => ['inn' => 'inn', 'tel' => 'phone', 'okved' => 'оквэд'],
    ]);

    // Stage the fixture where the reader expects it.
    Storage::disk('local')->put("{$file->target}/{$file->uniq_name}.{$file->ext}", file_get_contents($fixture));
    @unlink($fixture);

    (new LoaderJob($file->id))->handle(
        app(\App\Services\SpreadsheetReader::class),
        app(\App\Services\KeyDetector::class),
        app(AdapterRegistry::class),
    );

    expect(Lead::count())->toBe(2); // empty row skipped

    $r1 = Lead::query()->where('inn', '7707083893')->first();
    expect($r1->user_id)->toBe($user->id);
    expect($r1->phone)->toBe('+7 495 000-00-00');
    expect($r1->okved)->toBe('62.01');
    expect($r1->company_name)->toBe('ООО Ромашка');

    $r2 = Lead::query()->where('inn', '500100732259')->first();
    expect($r2->okved)->toBe('62.01.1');

    Queue::assertPushed(\App\Jobs\ScoreLeadJob::class, 2);
});

it('skips dispatch when the user has no active connection', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    // No UserConnect rows.

    $file = File::create([
        'user_id'           => $user->id,
        'name'              => 'leads.xlsx',
        'uniq_name'         => 'fixture',
        'target'            => "uploads/u_{$user->id}",
        'ext'               => 'xlsx',
        'is_new'            => true,
        'detected_columns'  => ['inn' => 'inn', 'tel' => 'phone', 'okved' => 'оквэд'],
    ]);

    $fixture = sys_get_temp_dir().'/leadflow-fixture-'.uniqid().'.xlsx';
    writeFixtureXlsx($fixture, [
        ['company', 'inn', 'phone'],
        ['ООО Ромашка', '7707083893', '+7 495 000-00-00'],
    ]);
    Storage::disk('local')->put("{$file->target}/{$file->uniq_name}.{$file->ext}", file_get_contents($fixture));
    @unlink($fixture);

    (new LoaderJob($file->id))->handle(
        app(\App\Services\SpreadsheetReader::class),
        app(\App\Services\KeyDetector::class),
        app(AdapterRegistry::class),
    );

    expect(Lead::count())->toBe(1);
    Queue::assertNothingPushed();
});