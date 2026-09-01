<?php

namespace App\Models;

use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LearningResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'provider', 'slug', 'provider_type', 'delivery_mode', 'url',
        'cost_min_cents', 'cost_max_cents', 'currency', 'cost_note',
        'duration_weeks', 'credential_type', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'provider_type' => ProviderType::class,
            'cost_min_cents' => 'integer',
            'cost_max_cents' => 'integer',
            'duration_weeks' => 'integer',
        ];
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot(['impact_weight'])
            ->withTimestamps();
    }
}
