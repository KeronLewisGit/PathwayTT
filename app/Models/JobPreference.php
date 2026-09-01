<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'industry_id', 'work_arrangements', 'employment_types',
        'seniority', 'min_salary_cents', 'min_salary_currency', 'min_salary_period',
    ];

    protected function casts(): array
    {
        return [
            'work_arrangements' => 'array',
            'employment_types' => 'array',
            'min_salary_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }
}
