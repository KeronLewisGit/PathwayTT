<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    protected $fillable = ['user_id', 'key', 'points', 'earned_at', 'seen'];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'earned_at' => 'datetime',
            'seen' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
