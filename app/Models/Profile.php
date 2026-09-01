<?php

namespace App\Models;

use App\Enums\QualificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'full_name', 'phone', 'region', 'summary', 'years_experience',
        'highest_education_level', 'has_nis', 'has_bir', 'has_drivers_permit',
        'has_police_certificate', 'willing_to_relocate', 'availability_date',
    ];

    protected function casts(): array
    {
        return [
            'highest_education_level' => QualificationType::class,
            'has_nis' => 'boolean',
            'has_bir' => 'boolean',
            'has_drivers_permit' => 'boolean',
            'has_police_certificate' => 'boolean',
            'willing_to_relocate' => 'boolean',
            'availability_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot(['proficiency', 'years_used', 'evidence_source', 'is_user_edited'])
            ->withTimestamps();
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function workHistories(): HasMany
    {
        return $this->hasMany(WorkHistory::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }
}
