<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobSyncRun extends Model
{
    protected $fillable = [
        'source', 'started_at', 'finished_at',
        'fetched_count', 'created_count', 'updated_count', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'fetched_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
        ];
    }
}
