<?php

declare(strict_types=1);

use App\Jobs\LoaderJob;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
});

function makeXlsxUpload(string $originalName): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'lf-up-');
    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setCellValue('A1', 'inn');
    $sheet->setCellValue('B1', 'phone');
    $sheet->setCellValue('C1', 'оквэд');
    $sheet->setCellValue('A2', '7707083893');
    $sheet->setCellValue('B2', '+7 495 000-00-00');
    $sheet->setCellValue('C2', '62.01');
    (new Xlsx($ss))->save($tmp);
    $ss->disconnectWorksheets();

    return new UploadedFile($tmp, $originalName, null, null, true);
}

it('stores the file, runs detector and dispatches LoaderJob', function (): void {
    $user = User::factory()->create();
    $uploaded = makeXlsxUpload('leads.xlsx');

    $file = app(\App\Services\FileUploadService::class)->store($user, $uploaded);

    expect($file)->toBeInstanceOf(File::class);
    expect($file->user_id)->toBe($user->id);
    expect($file->name)->toBe('leads.xlsx');
    expect($file->ext)->toBe('xlsx');
    expect($file->is_new)->toBeTrue();
    expect($file->target)->toBe("uploads/u_{$user->id}");
    Storage::disk('local')->assertExists("{$file->target}/{$file->uniq_name}.{$file->ext}");

    expect($file->detected_columns)->toMatchArray([
        'inn'   => 'inn',
        'tel'   => 'phone',
        'okved' => 'оквэд',
    ]);

    Queue::assertPushed(LoaderJob::class, fn ($job) => $job->fileId === $file->id);
});

it('rejects unsupported extensions', function (): void {
    $user = User::factory()->create();
    $uploaded = new UploadedFile(
        tempnam(sys_get_temp_dir(), 'lf-up-'),
        'bad.exe',
        null,
        null,
        true,
    );

    expect(fn () => app(\App\Services\FileUploadService::class)->store($user, $uploaded))
        ->toThrow(InvalidArgumentException::class);
});