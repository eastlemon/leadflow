<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'inn', 'phone', 'email',
        'first_name', 'last_name', 'middle_name',
        'company_name', 'city', 'region', 'okved',
        'extra', 'source',
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(LeadJob::class);
    }
}
