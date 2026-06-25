<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Auto-detects which column in a spreadsheet row holds ИНН, phone, ОКВЭД.
 *
 * Direct port of the legacy TellfaxHelper::getKeys/valid* trio from the
 * Yii2 codebase. Rules:
 *
 *  - iterate row cells once, in order
 *  - first cell matching validInn()  → INN column
 *  - first cell matching validPhone() → phone column
 *  - first cell matching validOkved() → ОКВЭД column
 *  - early-exit once all three are found
 *
 * Validation uses Russian rules (10/12-digit ИНН checksum, +7/8 phones,
 * ОКВЭД 4-6 digits with optional XX.XX[.X] format).
 *
 * Black-/white-list support preserves the prefix-match behavior of the
 * Yii2 helper (a 5-digit prefix of an ИНН is enough to flag it).
 */
class KeyDetector
{
    /**
     * @param  array<int|string, mixed>  $row
     * @return array{inn: int|string|null, tel: int|string|null, okved: int|string|null}
     */
    public function detect(array $row): array
    {
        $found = ['inn' => null, 'tel' => null, 'okved' => null];

        foreach ($row as $key => $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }
            $cell = (string) $value;
            if ($cell === '') {
                continue;
            }

            // Probe the cell in priority order so a single cell doesn't get
            // mis-bucketed into two slots. ИНН is the most distinctive (10/12
            // digits with checksum), so it claims the cell first; if it's not
            // an ИНН we then try phone, then ОКВЭД.
            $asInn = $this->validInn($cell);
            if ($found['inn'] === null && $asInn) {
                $found['inn'] = $key;
                continue; // a 10-digit ИНН would also pass validPhone() — skip it
            }

            if ($found['tel'] === null && $this->validPhone($cell)) {
                $found['tel'] = $key;
                continue;
            }

            if ($found['okved'] === null && $this->validOkved($cell)) {
                $found['okved'] = $key;
            }

            if ($found['inn'] !== null && $found['tel'] !== null && $found['okved'] !== null) {
                break;
            }
        }

        return $found;
    }

    /**
     * Re-validate the resolved field values from a row.
     *
     * `detect()` finds which columns hold inn/tel/okved. This second pass
     * confirms the actual cell contents pass our validators. Without it
     * a column named "INN" but containing 'some text' would slip through
     * into Lead::create() — exactly what TellFaxHelper::getKeys was
     * guarding against in the Yii2 code.
     *
     * Returns the list of fields that failed validation; empty list = OK.
     *
     * @param  array<string, string>  $row
     * @param  array<string, int|string|null>  $mapping  output of detect()
     * @return list<string>
     */
    public function validateResolved(array $row, array $mapping): array
    {
        $errors = [];

        $inn = $this->resolve($row, $mapping, 'inn');
        if ($inn !== null && ! $this->validInn($inn)) {
            $errors[] = 'inn';
        }

        $tel = $this->resolve($row, $mapping, 'tel');
        if ($tel !== null && ! $this->validPhone($tel)) {
            $errors[] = 'tel';
        }

        $okved = $this->resolve($row, $mapping, 'okved');
        if ($okved !== null && ! $this->validOkved($okved)) {
            $errors[] = 'okved';
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, int|string|null>  $mapping
     */
    private function resolve(array $row, array $mapping, string $field): ?string
    {
        $key = $mapping[$field] ?? null;
        if ($key === null) {
            return null;
        }
        $value = $row[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * ИНН checksum (10 digits — юрлица, 12 — физлица/ИП).
     * Coefficients are pre-computed per Russian tax rules.
     */
    public function validInn(string $inn): bool
    {
        if ($inn === '' || ! ctype_digit($inn)) {
            return false;
        }
        $len = strlen($inn);
        if ($len !== 10 && $len !== 12) {
            return false;
        }

        static $coeff10 = [2, 4, 10, 3, 5, 9, 4, 6, 8];
        static $coeff11 = [7, 2, 4, 10, 3, 5, 9, 4, 6, 8];
        static $coeff12 = [3, 7, 2, 4, 10, 3, 5, 9, 4, 6, 8];

        $checkDigit = static function (string $inn, array $coeff): int {
            $n = 0;
            foreach ($coeff as $i => $k) {
                $n += $k * (int) $inn[$i];
            }

            return $n % 11 % 10;
        };

        if ($len === 10) {
            return $checkDigit($inn, $coeff10) === (int) $inn[9];
        }

        return $checkDigit($inn, $coeff11) === (int) $inn[10]
            && $checkDigit($inn, $coeff12) === (int) $inn[11];
    }

    /**
     * Russian phone number: 10-11 digits, must start with 7 or 8.
     * Anything else (foreign numbers, short codes) is rejected.
     */
    public function validPhone(string $tel): bool
    {
        if ($tel === '') {
            return false;
        }
        $digits = preg_replace('/\D/', '', $tel);
        $len = strlen($digits);
        if ($len < 10 || $len > 11) {
            return false;
        }

        return $digits[0] === '7' || $digits[0] === '8';
    }

    /**
     * ОКВЭД code: 4-6 digits, optional XX.XX[.X] formatting, "ОКВЭД" prefix tolerated.
     */
    public function validOkved(string $okved): bool
    {
        $okved = trim($okved);
        if ($okved === '') {
            return false;
        }

        // Strip leading "ОКВЭД" if the user pasted the label too.
        if (mb_stripos($okved, 'ОКВЭД', 0, 'UTF-8') === 0) {
            $okved = trim(mb_substr($okved, 5, null, 'UTF-8'));
        }

        $digitsOnly = preg_replace('/\D/', '', $okved);
        $len = strlen($digitsOnly);
        if ($len < 4 || $len > 6) {
            return false;
        }

        if (str_contains($okved, '.')) {
            return (bool) preg_match('/^\d{2}\.\d{2}(\.\d{1,3})?$/', $okved);
        }

        return ctype_digit($okved);
    }

    /**
     * Returns true when the ИНН is NOT present in the given blacklist.
     * Empty blacklist → allow everything.
     *
     * @param  string[]|string  $list  either newline-separated string or array
     */
    public function isAllowedByBlackList(string $inn, array|string $list, bool $prefixMatch = true): bool
    {
        return ! $this->isInList($inn, $list, $prefixMatch);
    }

    /**
     * Returns true when the ИНН IS present in the given whitelist.
     * Empty whitelist → nothing allowed (intentional: empty means "no whitelist configured").
     *
     * @param  string[]|string  $list
     */
    public function isAllowedByWhiteList(string $inn, array|string $list, bool $prefixMatch = true): bool
    {
        return $this->isInList($inn, $list, $prefixMatch);
    }

    /**
     * @param  string[]|string  $list
     */
    private function isInList(string $inn, array|string $list, bool $prefixMatch): bool
    {
        $patterns = is_array($list) ? $list : (preg_split('/[\s,]+/', (string) $list, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        if ($patterns === []) {
            return false;
        }

        if (in_array($inn, $patterns, true)) {
            return true;
        }

        if (! $prefixMatch) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if ($pattern !== '' && str_starts_with($inn, $pattern)) {
                return true;
            }
        }

        return false;
    }
}