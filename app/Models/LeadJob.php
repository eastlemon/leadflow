<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadJob extends Model
{
    use HasFactory;

    protected $table = 'lead_jobs';

    protected $fillable = [
        'lead_id', 'system_name', 'stage', 'status',
        'external_id', 'error', 'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    public const STAGE_SCORE = 'score';
    public const STAGE_SEND  = 'send';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_OK         = 'ok';
    public const STATUS_FAILED     = 'failed';

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
