<?php

declare(strict_types=1);

namespace App\Scoring;

/**
 * Typed per-bank pre-flight configuration.
 *
 * Direct port of the relevant `tune` keys from the legacy
 * `models/scoring/*.php` Yii2 classes. The legacy code used string-typed
 * values everywhere ('yes'/'no', '-' for empty, int-as-string). Here we
 * normalize on construct so the rest of the system can rely on real
 * booleans / ints / arrays.
 *
 * Empty lists / disabled flags mean "rule does not apply" — the
 * corresponding Rule will short-circuit and the lead passes.
 */
final class ScoringConfig
{
    /**
     * @param  list<string>  $innBlacklist   full or prefix INNs to block
     * @param  list<string>  $okvedBlacklist full or prefix ОКВЭД codes to block
     * @param  list<string>  $innWhitelist   full or prefix INNs allowed (empty = no whitelist)
     * @param  bool          $skipExisting   when true, reject leads whose INN is already in our leads table
     * @param  int|null      $duplicateDays  when set, reject leads whose INN was processed within N days
     * @param  bool          $enabled        master switch — false short-circuits the whole pipeline to PASS
     */
    public function __construct(
        public readonly array $innBlacklist = [],
        public readonly array $okvedBlacklist = [],
        public readonly array $innWhitelist = [],
        public readonly bool $skipExisting = false,
        public readonly ?int $duplicateDays = null,
        public readonly bool $enabled = true,
    ) {
    }

    /**
     * Permissive default: all rules inactive, every lead passes pre-flight.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Normalize the legacy `tune` array shape into a typed config.
     *
     * Legacy keys (TellFax):
     *   - is_score        : bool toggle (false == pre-flight disabled entirely)
     *   - inn_skip_list   : string with separators, or list
     *   - okved_skip_list : string with separators, or list
     *   - inn_only        : string with separators, or list (whitelist)
     *   - skip_exist      : 'yes' / 'no'
     *   - off_days        : int or '-'  (period in days; '-' == no period check)
     *
     * @param  array<string, mixed>  $tune
     */
    public static function fromTune(array $tune): self
    {
        return new self(
            innBlacklist: self::splitList($tune['inn_skip_list'] ?? []),
            okvedBlacklist: self::splitList($tune['okved_skip_list'] ?? []),
            innWhitelist: self::splitList($tune['inn_only'] ?? []),
            skipExisting: self::asBool($tune['skip_exist'] ?? false),
            duplicateDays: self::asNullableInt($tune['off_days'] ?? null),
            enabled: self::asBool($tune['is_score'] ?? true),
        );
    }

    /**
     * Accepts either a list of strings or a single string with whitespace
     * / comma separators. Mirrors the `tune` storage style from TellFax.
     *
     * @return list<string>
     */
    private static function splitList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter(
                array_map(static fn ($v) => is_string($v) ? trim($v) : (string) $v, $raw),
                static fn (string $v) => $v !== '',
            ));
        }

        if (! is_string($raw)) {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $v) => $v !== '',
        ));
    }

    private static function asBool(mixed $raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $v = mb_strtolower(trim($raw));

            return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return (bool) $raw;
    }

    private static function asNullableInt(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === '-') {
            return null;
        }

        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        if (is_string($raw) && ctype_digit($raw)) {
            $i = (int) $raw;

            return $i > 0 ? $i : null;
        }

        return null;
    }
}
