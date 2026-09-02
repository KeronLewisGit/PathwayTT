<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * Tracked job (saved → applied → interviewing → offer / rejected).
 * Applying itself happens on the employer's site; this is the user's log.
 */
class Application extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'job_listing_id', 'status', 'applied_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'applied_at' => 'datetime',
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

    /**
     * Move along the pipeline. Only the forward transitions defined on
     * ApplicationStatus are allowed; applied_at is stamped once.
     *
     * @throws InvalidArgumentException on an illegal transition
     */
    public function transitionTo(ApplicationStatus $status): void
    {
        if (! in_array($status, $this->status->nextStatuses(), true)) {
            throw new InvalidArgumentException(
                "Cannot move from {$this->status->label()} to {$status->label()}."
            );
        }

        $this->forceFill([
            'status' => $status,
            'applied_at' => $status === ApplicationStatus::Applied ? now() : $this->applied_at,
        ])->save();
    }
}
