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

    /** Seniority vocabulary shared by admin forms, preferences and matching. */
    public const SENIORITIES = [
        'entry' => 'Entry',
        'mid' => 'Mid',
        'senior' => 'Senior',
        'manager' => 'Manager',
    ];

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

    /** Filter by title, company or location (simple LIKE search — fine at this scale). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $q->where('title', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('location_text', 'like', $like);
        });
    }

    /**
     * SQL twin of ineligibilityReason(): excludes listings a T&T resident
     * cannot apply to. Keep the two in sync.
     */
    public function scopeEligibleFromTT(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('geo_eligibility')
                ->orWhere('geo_eligibility', GeoEligibility::Worldwide->value)
                ->orWhere(function (Builder $country) {
                    $country->where('geo_eligibility', GeoEligibility::CountryRestricted->value)
                        ->where(fn (Builder $c) => $c->whereNull('country')->orWhere('country', 'TT'));
                })
                ->orWhere(function (Builder $region) {
                    $region->where('geo_eligibility', GeoEligibility::RegionRestricted->value)
                        ->where(fn (Builder $r) => $r->whereNull('is_open_to_caribbean')->orWhere('is_open_to_caribbean', true));
                });
        });
    }

    public function isOpen(): bool
    {
        return $this->is_active && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * Why a Trinidad & Tobago resident cannot apply, or null if eligible.
     * This is a HARD filter (used by matching in Phase 4), not a low score:
     * a US-only remote job must never be shown as eligible to a T&T user.
     */
    public function ineligibilityReason(): ?string
    {
        if ($this->geo_eligibility === GeoEligibility::CountryRestricted && $this->country !== null && $this->country !== 'TT') {
            return "Restricted to applicants in {$this->country} — not open to Trinidad & Tobago residents";
        }

        if ($this->geo_eligibility === GeoEligibility::RegionRestricted && $this->is_open_to_caribbean === false) {
            return 'Region-restricted and not open to Caribbean applicants';
        }

        return null;
    }
}
