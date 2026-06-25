<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Adapters\AdapterRegistry;
use App\Models\File;
use App\Models\Lead;
use App\Services\KeyDetector;
use App\Services\SpreadsheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads one uploaded File and turns every data row into a Lead.
 *
 * Resolution order for cell → field:
 *  1. Prefer positions from File.detected_columns (fixed at upload time).
 *  2. If absent, run KeyDetector on each row (slow but correct).
 *
 * Each new Lead is then fan-out'd into ScoreLeadJob for every active bank
 * the user has configured. This mirrors SkorozvonWebhookProcessor so both
 * ingestion paths end in the same scoring pipeline.
 */
class LoaderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(
        public readonly int $fileId,
    ) {
    }

    public function handle(SpreadsheetReader $reader, KeyDetector $detector, AdapterRegistry $registry): void
    {
        $file = File::find($this->fileId);
        if (! $file) {
            Log::warning('LoaderJob: file not found', ['file_id' => $this->fileId]);

            return;
        }

        $absolute = Storage::disk('local')->path("{$file->target}/{$file->uniq_name}.{$file->ext}");
        $detected = $file->detected_columns ?: null;

        $count = 0;
        $skipped = 0;
        $invalid = 0;
        try {
            foreach ($reader->rows($absolute) as $row) {
                $mapping = $detected ?? $detector->detect($row);

                // Confirm the resolved cell values actually pass validation.
                // A column named "INN" can still hold garbage.
                $badFields = $detector->validateResolved($row, $mapping);

                $inn = $this->pull($row, $mapping, 'inn');
                if ($inn === null) {
                    $skipped++;
                    continue; // no INN → not a valid lead, skip silently
                }
                if (in_array('inn', $badFields, true)) {
                    $invalid++;
                    Log::warning('LoaderJob: row with invalid INN, dropped', [
                        'file_id' => $file->id,
                        'inn'     => $inn,
                    ]);
                    continue;
                }

                $phone = $this->pull($row, $mapping, 'tel');
                if ($phone !== null && in_array('tel', $badFields, true)) {
                    Log::warning('LoaderJob: row with invalid phone, kept (inn still valid)', [
                        'file_id' => $file->id,
                        'phone'   => $phone,
                    ]);
                    $phone = null;
                }

                $lead = Lead::create([
                    'user_id'      => $file->user_id,
                    'inn'          => $inn,
                    'phone'        => $phone,
                    'okved'        => $this->pull($row, $mapping, 'okved'),
                    'email'        => $row['email'] ?? null,
                    'first_name'   => $row['first_name'] ?? $row['name'] ?? null,
                    'last_name'    => $row['last_name']   ?? null,
                    'middle_name'  => $row['middle_name'] ?? null,
                    'company_name' => $row['company']     ?? $row['company_name'] ?? null,
                    'city'         => $row['city']    ?? null,
                    'region'       => $row['region']  ?? null,
                    'extra'        => $row,
                    'source'       => 'upload:'.$file->id,
                ]);

                if ($lead->user_id !== null) {
                    foreach (array_keys($registry->allForUser($lead->user_id)) as $systemName) {
                        ScoreLeadJob::dispatch($lead->id, $systemName);
                    }
                }
                $count++;
            }

            Log::info('LoaderJob: processed file', [
                'file_id' => $file->id,
                'rows'    => $count,
                'skipped' => $skipped,
                'invalid' => $invalid,
            ]);
        } catch (Throwable $e) {
            Log::error('LoaderJob: failed', [
                'file_id' => $file->id,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Read a cell by detected column position, falling back to a header-name lookup.
     *
     * @param  array<string, string>  $row
     * @param  array<string, int|string|null>  $mapping
     */
    private function pull(array $row, array $mapping, string $field): ?string
    {
        $key = $mapping[$field] ?? null;
        if ($key === null) {
            return null;
        }

        $value = $row[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }
}