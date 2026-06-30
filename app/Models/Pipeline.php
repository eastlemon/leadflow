<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pipeline groups one provider (source of leads) with multiple receivers (banks).
 *
 * Replaces the legacy `connect` tree where `id_parent = NULL` was the provider
 * and children were the receivers. Here the relationship is explicit and flat:
 *
 *   Pipeline "Свежая база" (skorozvon)
 *     ├── PipelineReceiver: alfa  (tune: {delay: 1, ...})
 *     ├── PipelineReceiver: psb   (tune: {delay: 5, ...})
 *     └── PipelineReceiver: vtb   (tune: {...})
 *
 * `provider_config` holds credentials for the provider side
 * (e.g. Skorozvon api_key, file_upload settings).
 * Each receiver's `tune` holds the bank-specific config for THIS pipeline.
 */
class Pipeline extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'name',
        'provider_config',
        'is_active',
    ];

    protected $casts = [
        'provider_config' => 'array',
        'is_active'       => 'boolean',
    ];

    /* ── Relationships ──────────────────────────────────────────── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(PipelineReceiver::class);
    }

    public function activeReceivers(): HasMany
    {
        return $this->receivers()->where('is_active', true);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /* ── Helpers ───────────────────────────────────────────────── */

    /**
     * The system names of all active receivers in this pipeline.
     *
     * @return string[] e.g. ['alfa', 'psb', 'vtb']
     */
    public function activeReceiverNames(): array
    {
        return $this->activeReceivers->pluck('system_name')->values()->all();
    }

    /**
     * Look up a specific receiver by system name.
     */
    public function receiver(string $systemName): ?PipelineReceiver
    {
        return $this->receivers->firstWhere('system_name', $systemName);
    }
}