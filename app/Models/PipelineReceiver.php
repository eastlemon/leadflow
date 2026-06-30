<?php

declare(strict_types=1);

namespace App\Models;

use App\Adapters\Contracts\BankAdapter;
use App\Adapters\AdapterRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bank (receiver) inside a pipeline, with its own tune.
 *
 * The same bank (e.g. alfa) can appear in multiple pipelines with
 * different `tune` values — different scoring rules, delay settings,
 * API credentials, etc.
 */
class PipelineReceiver extends Model
{
    protected $fillable = [
        'pipeline_id',
        'system_name',
        'is_active',
        'tune',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tune'      => 'array',
    ];

    /* ── Relationships ──────────────────────────────────────────── */

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /* ── Helpers ───────────────────────────────────────────────── */

    /**
     * Build the adapter for this receiver using the pipeline's user context.
     *
     * Returns null if the adapter is not registered or the pipeline's user
     * lacks credentials.
     */
    public function adapter(): ?BankAdapter
    {
        $settings = array_merge(
            (array) $this->tune,
            ['system_name' => $this->system_name],
        );

        return app(AdapterRegistry::class)->get($this->system_name, $settings);
    }

    /**
     * Human label: display_name from tune, or fall back to system_name.
     */
    public function label(): string
    {
        return $this->tune['display_name'] ?? $this->system_name;
    }
}