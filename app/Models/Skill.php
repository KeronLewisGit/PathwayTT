<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'category', 'aliases'];

    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class)
            ->withPivot(['proficiency', 'years_used', 'evidence_source', 'is_user_edited'])
            ->withTimestamps();
    }

    public function jobListings(): BelongsToMany
    {
        return $this->belongsToMany(JobListing::class)
            ->withPivot(['is_required', 'weight'])
            ->withTimestamps();
    }

    public function learningResources(): BelongsToMany
    {
        return $this->belongsToMany(LearningResource::class)
            ->withPivot(['impact_weight'])
            ->withTimestamps();
    }

    /** All matchable strings for this skill: canonical name + aliases, lowercased. */
    public function matchTerms(): array
    {
        return collect([$this->name, ...($this->aliases ?? [])])
            ->map(fn (string $term) => mb_strtolower(trim($term)))
            ->unique()
            ->values()
            ->all();
    }
}
