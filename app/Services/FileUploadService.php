<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\LoaderJob;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded spreadsheets and primes them for processing.
 *
 * Flow:
 *   1. Save the file to disk under  uploads/u_{user_id}/{uniq}.{ext}
 *   2. Create the `files` row with is_new=true
 *   3. Run KeyDetector on the first data row to fix column positions
 *   4. Persist those positions in files.detected_columns
 *   5. Dispatch LoaderJob (reads the file, creates one Lead per row)
 *
 * Allowed extensions: xlsx, xls, ods, csv (configured in Filament rule).
 */
class FileUploadService
{
    /** @var array<int, string> */
    public const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'ods', 'csv'];

    public function __construct(
        private readonly SpreadsheetReader $reader,
        private readonly KeyDetector $detector,
    ) {
    }

    public function store(User $user, UploadedFile $uploaded, ?int $connectId = null): File
    {
        $ext = strtolower($uploaded->getClientOriginalExtension());
        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Unsupported file extension: .{$ext}");
        }

        $uniq = Str::lower(Str::random(9));
        $target = "uploads/u_{$user->id}";
        $disk = Storage::disk('local');

        $disk->makeDirectory($target);
        $disk->put("{$target}/{$uniq}.{$ext}", $uploaded->getContent());

        $file = File::create([
            'user_id'           => $user->id,
            'connect_id'        => $connectId,
            'name'              => $uploaded->getClientOriginalName(),
            'uniq_name'         => $uniq,
            'target'            => $target,
            'ext'               => $ext,
            'is_new'            => true,
            'detected_columns'  => null,
        ]);

        // Try to detect columns from the first row; on failure we leave
        // detected_columns null and let LoaderJob fall back to a full scan.
        try {
            $absolute = $disk->path("{$target}/{$uniq}.{$ext}");
            foreach ($this->reader->rows($absolute) as $firstRow) {
                $detected = $this->detector->detect($firstRow);
                $file->update(['detected_columns' => $detected]);
                break;
            }
        } catch (\Throwable $e) {
            // Detection is best-effort — LoaderJob will retry on every row.
            report($e);
        }

        LoaderJob::dispatch($file->id);

        return $file;
    }
}