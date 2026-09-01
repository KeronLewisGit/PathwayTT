<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillGapPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'target_industry_id', 'generated_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetIndustry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'target_industry_id');
    }
}
