<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use RuntimeException;

/**
 * Reads a spreadsheet (xlsx/xls/ods/csv) into an iterable stream of rows.
 *
 * The first row is treated as headers; data rows start from row 2.
 * For csv we honor "," as default delimiter; xls/xlsx/ods use IOFactory auto-detect.
 *
 * Output rows are associative arrays keyed by header name.
 * If the header row has duplicate names, the later ones win (last-write).
 * Empty trailing rows are skipped.
 *
 * Streaming via a Generator keeps memory flat for large files — important
 * since uploads can hit 1 MB of rows * many files.
 */
class SpreadsheetReader
{
    /**
     * @return Generator<int, array<string, string>>
     */
    public function rows(string $absolutePath): Generator
    {
        if (! is_readable($absolutePath)) {
            throw new RuntimeException("Spreadsheet not readable: {$absolutePath}");
        }

        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
        } catch (ReaderException $e) {
            throw new RuntimeException("Unsupported spreadsheet format: {$absolutePath}", previous: $e);
        }

        $reader->setReadDataOnly(true);

        try {
            $spreadsheet = $reader->load($absolutePath);
        } catch (ReaderException $e) {
            throw new RuntimeException("Failed to load spreadsheet: {$absolutePath}", previous: $e);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        if ($highestRow < 2) {
            return; // header-only or empty file
        }

        // Read header row once, normalize to strings.
// We lowercase+trim headers so callers can use a stable canonical key
// ('inn', 'phone', 'email', 'company', ...) regardless of how the spreadsheet
// capitalised them ('INN', ' Phone ', 'E-mail', ...).
        $headers = [];
        for ($col = 1; $col <= $highestColIdx; $col++) {
            $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
            $raw = $sheet->getCell($coordinate)->getValue();
            $headers[$col] = strtolower(trim((string) ($raw ?? '')));
        }

        for ($row = 2; $row <= $highestRow; $row++) {
            $assoc = [];
            $hasAny = false;
            for ($col = 1; $col <= $highestColIdx; $col++) {
                $header = $headers[$col];
                if ($header === '') {
                    continue;
                }
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
                $value = $sheet->getCell($coordinate)->getValue();
                if ($value === null || $value === '') {
                    $assoc[$header] = '';
                    continue;
                }
                $hasAny = true;
                $assoc[$header] = trim((string) $value);
            }
            if (! $hasAny) {
                continue;
            }
            yield $row - 1 => $assoc;
        }
    }
}