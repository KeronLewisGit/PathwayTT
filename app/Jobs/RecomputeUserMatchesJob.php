<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Engagement\AchievementService;
use App\Services\Matching\MatchRecomputeService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recompute one user's matches. Dispatched whenever their profile or
 * preferences change, or new jobs are ingested. ShouldBeUnique collapses a
 * burst of edits (each field save dispatches) into a single queued run.
 */
class RecomputeUserMatchesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** Seconds the unique lock is held while queued. */
    public int $uniqueFor = 60;

    public function __construct(public readonly int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(MatchRecomputeService $recompute): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return; // account deleted while queued
        }

        $recompute->recomputeForUser($user);

        // Score-based milestones ("On the board") can only be reached here.
        app(AchievementService::class)->evaluate($user);
    }
}
