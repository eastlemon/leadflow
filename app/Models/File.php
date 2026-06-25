<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uploaded spreadsheet waiting to be processed.
 *
 * Mirrors the Yii2 `file` table:
 *  - `uniq_name` is a short random prefix used in the storage path
 *  - `target` is the directory relative to the disk root, e.g. "uploads/u_42"
 *  - `detected_columns` is filled once by KeyDetector at upload time
 *  - `is_new` flips to false once an admin has looked at it (not a state machine)
 */
class File extends Model
{
    use HasFactory;

    protected $table = 'files';

    protected $fillable = [
        'user_id', 'connect_id', 'name', 'uniq_name', 'target', 'ext',
        'is_new', 'detected_columns',
    ];

    protected $casts = [
        'is_new' => 'boolean',
        'detected_columns' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connect(): BelongsTo
    {
        return $this->belongsTo(Connect::class);
    }

    /**
     * Full path on the local disk.
     */
    public function absolutePath(string $diskRoot): string
    {
        return rtrim($diskRoot, '/').'/'.trim($this->target, '/').'/'.$this->uniq_name.'.'.$this->ext;
    }
}