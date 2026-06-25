<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single user's connection to a single bank.
 *
 * The legacy `connects` table held one row per (bank, tunings) globally;
 * here we allow every user to have their own credentials and active flag.
 * `tune` is the per-bank JSON blob (api_url, api_key, email, password, ...).
 */
class UserConnect extends Model
{
    use HasFactory;

    protected $table = 'user_connects';

    protected $fillable = [
        'user_id',
        'system_name',
        'is_active',
        'display_name',
        'tune',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tune'      => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Convenience: returns the user-facing label, falling back to system_name.
     */
    public function label(): string
    {
        return $this->display_name ?: $this->system_name;
    }
}
