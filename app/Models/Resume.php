<?php

namespace App\Models;

use App\Enums\ParseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Resume extends Model
{
    use HasFactory;

    /** Resumes are PII: deleting the record hard-deletes the file too. */
    protected static function booted(): void
    {
        static::deleting(function (Resume $resume) {
            Storage::disk(config('resume.disk'))->delete($resume->path);
        });
    }

    protected $fillable = [
        'user_id', 'original_filename', 'path', 'mime_type', 'size_bytes',
        'extracted_text', 'parse_status', 'parse_error', 'parsed_at',
    ];

    protected function casts(): array
    {
        return [
            'parse_status' => ParseStatus::class,
            'parsed_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
