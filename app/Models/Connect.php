<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connect extends Model
{
    use HasFactory;

    protected $table = 'connects';

    protected $fillable = [
        'system_name',
        'display_name',
        'is_active',
        'tune',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tune'      => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(LeadJob::class, 'system_name', 'system_name');
    }
}
