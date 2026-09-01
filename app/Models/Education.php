<?php

namespace App\Models;

use App\Enums\QualificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Education extends Model
{
    use HasFactory;

    protected $table = 'educations';

    protected $fillable = [
        'profile_id', 'institution', 'qualification_type', 'field',
        'completed_at', 'is_user_edited',
    ];

    protected function casts(): array
    {
        return [
            'qualification_type' => QualificationType::class,
            'completed_at' => 'date',
            'is_user_edited' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
