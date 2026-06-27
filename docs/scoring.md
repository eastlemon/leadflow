# Bank Scoring (pre-flight)

The scoring subsystem is the **first line of defence** between an incoming
lead and the bank API. It runs a chain of local rules against a lead
*before* we pay for an HTTP call to the bank. A rule that says "this
lead is no good" stops the chain — the bank is never contacted.

This file explains the why, the pieces, and how to extend it.

---

## TL;DR

```text
                ┌────────────────────────────────────────┐
                │  ScoreLeadJob::handle(lead, systemName)│
                └─────────────────┬──────────────────────┘
                                  │
                                  ▼
                ┌────────────────────────────────────────┐
                │  BankScoringService::check(lead, cfg)  │
                │  ─ 1 short-circuit if cfg.enabled=false│
                │  ─ 2 run rules 1..N in order           │
                │  ─ 3 return first non-PASS, or PASS    │
                └─────────────────┬──────────────────────┘
                                  │
              ┌───────────────────┴────────────────────┐
              │ PASS (or DISABLED)                     │ blocks
              ▼                                        ▼
   ┌─────────────────────┐                 ┌──────────────────────┐
   │ $adapter->score()   │                 │ LeadJob status=OK    │
   │ HTTP call to bank   │                 │ error = human reason │
   └─────────────────────┘                 │ (no HTTP fires)      │
                                           └──────────────────────┘
```

The per-rule config lives in `user_connects.tune` (one JSON blob per
user+bank). `ConfigFactory` parses it once when the adapter is built;
`ScoreLeadJob` reads it back from the adapter (`$adapter->scoringConfig()`)
and runs the same rule list the factory would have used.

---

## Why this exists

The legacy TellFax (`/var/www/tellfax`) has a `models/scoring/*.php`
class per bank. Each one's `check()` method does the same six things in
slightly different orders:

1. Bail out if `is_score` is off.
2. Reject when ИНН is in the bank's `inn_skip_list` (blacklist).
3. Reject when ОКВЭД is in the bank's `okved_skip_list`.
4. Reject when ИНН is **not** in the bank's `inn_only` (whitelist).
5. Reject when the same ИНН was already processed (`skip_exist`).
6. Reject when the same ИНН was processed within `off_days` days.

Those live as ad-hoc per-class methods that the legacy `ScoringJob`
instantiates by string. Two problems: the logic is duplicated four
times, and the call sites have to `if ($check == 'ok' || 'duple') …`
against loose strings.

In LeadFlow each rule is a class. Per-bank pipelines are data — the
`ScoringConfigFactory` returns the right list of rules for the
`system_name`. The result is typed (`ScoringDecision` with a status
enum) so the job and the UI can switch on a real value, not a string
match.

---

## The pieces

### `App\Scoring\ScoringConfig` — the typed per-bank config

Readonly value object. The only thing that's "stored" is the raw
`tune` array on `user_connects`; `ScoringConfig::fromTune()` does the
parsing and validation once.

```php
final class ScoringConfig
{
    public function __construct(
        public readonly array  $innBlacklist   = [],   // list<string>
        public readonly array  $okvedBlacklist = [],   // list<string>
        public readonly array  $innWhitelist   = [],   // list<string>
        public readonly bool   $skipExisting   = false,
        public readonly ?int   $duplicateDays  = null,
        public readonly bool   $enabled        = true,
    ) { … }
}
```

Defaults = permissive (every rule inactive). If a user has no `tune`
JSON at all, scoring is a no-op and every lead passes.

#### `fromTune()` — the legacy format translator

The legacy `tune` shape is loose. It can have:

- **String-typed lists** with whitespace/comma separators:
  `"inn_skip_list" => "12345\n67890, 999"` → `['12345','67890','999']`.
- **Real arrays** of strings: `['12345','67890']` → same.
- **The magic string `-`** meaning "empty" — used everywhere in the
  legacy UI for "no value". `fromTune()` treats `-`, `''`, `null` as
  "this field is not set".
- **Boolean-ish strings**: `'yes'` / `'no'` / `'1'` / `'true'` /
  `'on'` for `is_score` and `skip_exist`.
- **Days as int or string or `-`**: `30`, `'30'`, `'-'`, `0` (which
  we treat as "no period check" — a 0-day window is meaningless).

`ScoringConfig::default()` is the same shape with everything inactive;
the rest of the system never has to null-check.

### `App\Scoring\ScoringDecision` — what a check returned

```php
final class ScoringDecision
{
    public const PASS      = 'pass';
    public const REJECTED  = 'rejected';
    public const DUPLICATE = 'duplicate';
    public const DISABLED  = 'disabled';

    public function __construct(
        public readonly string  $status,   // one of the four constants
        public readonly ?string $code = null,   // e.g. 'inn_blacklist'
        public readonly ?string $reason = null, // human-readable RU
    ) { … }

    public function isPass(): bool    { … }  // true only for PASS
    public function blocksSend(): bool { … } // any non-PASS verdict
}
```

`code` is stable (used in tests, the UI eventually). `reason` is for
humans (Russian, goes into `LeadJob.error`). The four factories
`ScoringDecision::pass() / rejected() / duplicate() / disabled()` are
the only sanctioned constructors.

### `App\Scoring\Contracts\ScoringRule` — the rule interface

```php
interface ScoringRule
{
    public function check(LeadData $lead, ScoringConfig $tune): ScoringDecision;
}
```

Stateless rules only need `$lead` and `$tune`. Rules that touch the DB
(`SkipExistingRule`, `DuplicatePeriodRule`) take their DB dependency
in their **constructor** — the interface stays slim, and you can wire
them through Laravel's container if you ever need to.

Every rule follows the same internal contract:

- **Bail out early** if the config field it cares about is inactive.
  This is what makes a rule "free" for banks that don't use it.
- Return `ScoringDecision::rejected($code, $reason)` with a stable
  `code` if the lead should be blocked.
- Otherwise return `ScoringDecision::pass()`.

---

## The five rules

All live in `app/Scoring/Rules/`.

### `InnBlacklistRule` — `code: inn_blacklist`

Rejects when the lead's ИНН (or an ИНН prefix) appears in
`tune.innBlacklist`. Uses `App\Services\KeyDetector::isAllowedByBlackList()`
so the prefix-match semantics match TellFax (5-digit prefix is enough
to flag a 10-digit ИНН).

Inactive when `innBlacklist` is empty.

### `OkvedBlacklistRule` — `code: okved_blacklist`

Same shape as the ИНН rule, but for ОКВЭД. A lead with no ОКВЭД is
**not** rejected — the bank can still decide on its own. Inactive when
`okvedBlacklist` is empty.

### `InnWhitelistRule` — `code: inn_whitelist`

Inverse of the blacklist. Active when `innWhitelist` is non-empty;
rejects when the lead's ИНН is **not** in the list. Inactive when
`innWhitelist` is empty (the empty-means-inactive convention is
intentional — otherwise an accidentally-empty whitelist would block
everything).

### `SkipExistingRule` — `code: duplicate`

Active when `skipExisting = true`. Queries the `leads` table for a
row with the same ИНН. The query is scoped to `leads.user_id` so
tenants don't trip each other (multi-user architecture).

Returns `ScoringDecision::duplicate()` so the UI can render the
"you already sent this" icon differently from a regular rejection.

### `DuplicatePeriodRule` — `code: duplicate`

Active when `duplicateDays` is a positive integer. Rejects when a
lead with the same ИНН was created within the last N days. Scoped to
the same `user_id`. Compares against `created_at`, not `updated_at` —
re-editing an old lead must not reset the cooldown.

Returns `ScoringDecision::duplicate()` for the same reason as above.

---

## Per-bank composition

`ScoringConfigFactory::forBank(systemName, tune)` returns a
`{config, rules}` array. The rule lists mirror TellFax's behaviour:

| Bank  | Rules                                                            | Why |
|-------|------------------------------------------------------------------|-----|
| `alfa`  | InnBlacklist → OkvedBlacklist → SkipExisting                  | Alfa filters by ОКВЭД class, not by whitelist. |
| `psb`   | InnBlacklist → InnWhitelist → SkipExisting → DuplicatePeriod | PSB is opt-in: a strict whitelist + cooldown for spam control. |
| `vtb`   | InnBlacklist → SkipExisting → DuplicatePeriod                | VTB has no whitelist but cares about cooldown. |
| `ural`  | InnWhitelist → SkipExisting → DuplicatePeriod                | Ural is whitelist-only. |

Adding a new bank = add a new case in `ScoringConfigFactory::rulesFor()`.

---

## Configuration: how a user turns rules on/off

All per-bank scoring lives in the `tune` JSON column of `user_connects`.
There's no separate settings table — a user changes their bank's tune
in the Filament admin and that's it.

Example `tune` for a PSB connection with a 7-day cooldown:

```json
{
  "api_url":     "https://api.lk.psb.services",
  "email":       "partner@example.com",
  "password":    "…",
  "is_score":    "yes",
  "inn_only":    "77070\n77071",
  "inn_skip_list": "-",
  "okved_skip_list": "",
  "skip_exist":  "yes",
  "off_days":    "7"
}
```

Same JSON works as the input to `ScoringConfig::fromTune()` and as the
settings array that `AdapterRegistry::getForUser()` passes to
`ConfigFactory::fromArray()`. The factory reads scoring keys and API
keys from the same blob — one source of truth per (user, bank).

The full set of recognised keys (all optional, all have sensible
defaults):

| key              | type             | meaning |
|------------------|------------------|---------|
| `is_score`       | bool-ish string  | master switch. `'no'` short-circuits to DISABLED — every lead passes. |
| `inn_skip_list`  | string or array  | blacklist of ИНН / prefixes. |
| `okved_skip_list`| string or array  | blacklist of ОКВЭД / prefixes. |
| `inn_only`       | string or array  | whitelist of ИНН / prefixes. Empty = inactive. |
| `skip_exist`     | bool-ish string  | when true, reject if same ИНН exists for this user. |
| `off_days`       | int or `'-'`     | cooldown in days for the same ИНН. |

---

## The flow inside `ScoreLeadJob`

`app/Jobs/ScoreLeadJob.php`, in order:

1. Load the `Lead` by id. Bail if missing (warn-log).
2. Resolve the user's adapter via `AdapterRegistry::getForUser()`. Skip
   silently if the user has no active connection.
3. Create a `LeadJob` row in `STATUS_PROCESSING` for the audit trail.
4. Read `$adapter->scoringConfig()` — this is the typed
   `ScoringConfig` the factory built when the adapter was constructed.
5. Build the rule list for the bank from
   `ScoringConfigFactory::forBank(systemName, [])`. We pass an empty
   `tune` here because the config is already carried by the adapter —
   the rules themselves are stateless and bank-name-keyed.
6. Hand both to `BankScoringService::check()`.
7. If the decision `blocksSend()` — write `LeadJob` with
   `status = STATUS_OK`, `error = $decision->reason`, `finished_at = now()`.
   **No HTTP call is made.**
8. Otherwise call `$adapter->score($lead)`. The result is translated
   to a `LeadJob` row as before.

The job's `tries` / `backoff` is unrelated to per-request retries —
that's `BankHttpClient`'s job. The queue retry is for job-level
exceptions (DB blip, process kill), not for HTTP blips.

---

## Adding a new rule

1. Create `app/Scoring/Rules/MyRule.php` implementing
   `App\Scoring\Contracts\ScoringRule`. Add a `public function check(LeadData, ScoringConfig): ScoringDecision`.
2. If your rule needs configuration, add a field to
   `ScoringConfig` and parse it in `fromTune()`.
3. Add the rule to the bank(s) that should run it in
   `ScoringConfigFactory::rulesFor()`.
4. Add a test in `tests/Unit/Scoring/ScoringRulesTest.php`:
   - rule inactive → PASS
   - rule active + match → REJECTED with the right code
   - rule active + no match → PASS

The orchestrator is generic — no changes needed in
`BankScoringService` or `ScoreLeadJob`.

---

## Adding a new bank

1. Create `app/Adapters/Configs/MyConfig.php` extending
   `AdapterConfig`, with `systemName: 'my'` and a
   `displayName`. Add `?ScoringConfig $scoring = null` to the
   constructor and pass it to `parent::__construct()`.
2. Create `app/Adapters/Banks/MyAdapter.php` implementing
   `BankAdapter`. Use the `BankAdapterHelpers` trait so
   `scoringConfig()` is free.
3. Register the class in `app/Adapters/AdapterRegistry::MAP`.
4. Add the `makeMy()` branch in `ConfigFactory` that builds your
   config from the settings array.
5. Add a `case 'my' =>` branch in
   `ScoringConfigFactory::rulesFor()` with the rule list you want.
6. Tests:
   - `tests/Unit/Adapters/ConfigFactoryTest.php` — covers
     `ConfigFactory::fromArray()`.
   - `tests/Unit/Scoring/ScoringConfigFactoryTest.php` — covers
     `ScoringConfigFactory::forBank()`.
   - An adapter test under `tests/Feature/Adapters/`.

---

## Testing

- `tests/Unit/Scoring/ScoringConfigTest.php` — the `fromTune()`
  parser. Every legacy edge case (dash, empty, string-typed bool,
  off_days=0).
- `tests/Unit/Scoring/ScoringRulesTest.php` — one test per
  rule: inactive, match, no match. The DB-scoped rules
  (`SkipExistingRule`, `DuplicatePeriodRule`) use
  `User::factory()->create()` and `DB::table('leads')->insert()`
  with explicit `created_at` so the cooldown can be exercised.
- `tests/Unit/Scoring/BankScoringServiceTest.php` — verifies
  first-fail-wins, the DISABLED short-circuit, and the empty-rules
  no-op.
- `tests/Unit/Scoring/ScoringConfigFactoryTest.php` — verifies
  the per-bank rule composition matches the table above.
- `tests/Feature/Jobs/ScoreLeadJobTest.php` — two end-to-end
  cases that **don't** fake `Http::`: a blacklist short-circuits
  before any HTTP call, and a duplicate-period short-circuits
  before any HTTP call. If you ever accidentally move the
  pre-flight *after* the API call, these tests will catch it
  because the assertion will hang on the missing fake.

Run the scoring slice in isolation:

```bash
cd /var/www/leadflow
./vendor/bin/pest tests/Unit/Scoring tests/Feature/Jobs/ScoreLeadJobTest
```

---

## Common pitfalls

- **The bank whitelist vs blacklist has opposite empty semantics.**
  An empty blacklist means "nothing blacklisted, allow everything".
  An empty whitelist means "no whitelist configured, allow
  everything" too — but for a different reason (inverted
  semantics). Both rule classes handle the empty case as a no-op.
- **`off_days = 0` is silently treated as "no cooldown"**, not
  "cooldown of zero days" (which would block every lead). The
  `asNullableInt` parser enforces this.
- **ПСБ and Урал have a whitelist** — if you enable a `tune` for
  one of them and forget to populate `inn_only`, the bank will
  reject every lead. That's the same behaviour as TellFax; the
  scoring config just makes it explicit.
- **Pre-flight is per-bank.** The same lead can pass Alfa's
  pre-flight and fail PSB's whitelist — each bank runs its own
  pipeline independently. The `LeadJob` row per bank carries the
  per-bank decision.
- **`$lead->userId` is required for DB-scoped rules.** It comes
  from `Lead::user_id` and is propagated through `LeadData`.
  Without a user, `SkipExistingRule` and `DuplicatePeriodRule`
  match against the global leads table (no tenant filter) — fine
  for system-level jobs, but watch out if you ever dispatch a job
  with a lead that has no user.
