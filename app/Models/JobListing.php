<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\GeoEligibility;
use App\Enums\WorkArrangement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'source', 'source_job_id', 'title', 'company_name', 'company_id',
        'industry_id', 'work_arrangement', 'employment_type', 'location_text',
        'country', 'is_open_to_caribbean', 'geo_eligibility',
        'required_overlap_hours', 'seniority', 'salary_min_cents',
        'salary_max_cents', 'salary_currency', 'salary_period', 'description',
        'requirements', 'posted_at', 'closes_at', 'apply_url', 'raw_payload',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'work_arrangement' => WorkArrangement::class,
            'employment_type' => EmploymentType::class,
            'geo_eligibility' => GeoEligibility::class,
            'is_open_to_caribbean' => 'boolean',
            'required_overlap_hours' => 'integer',
            'salary_min_cents' => 'integer',
            'salary_max_cents' => 'integer',
            'requirements' => 'array',
            'raw_payload' => 'array',
            'posted_at' => 'datetime',
            'closes_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot(['is_required', 'weight'])
            ->withTimestamps();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(JobMatch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }
}
