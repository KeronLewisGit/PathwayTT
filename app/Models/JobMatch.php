<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'job_listing_id', 'score', 'score_breakdown',
        'missing_skills', 'is_eligible', 'ineligibility_reason', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'score_breakdown' => 'array',
            'missing_skills' => 'array',
            'is_eligible' => 'boolean',
            'computed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobListing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class);
    }
}
