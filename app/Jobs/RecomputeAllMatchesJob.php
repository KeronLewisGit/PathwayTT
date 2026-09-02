<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Fan-out after job ingestion: one RecomputeUserMatchesJob per user who
 * has a profile. Chunked so a 10k-user fan-out never loads them all.
 */
class RecomputeAllMatchesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        User::query()
            ->whereHas('profile')
            ->select('id')
            ->chunkById((int) config('matching.recompute_chunk_size', 100), function (Collection $users) {
                foreach ($users as $user) {
                    RecomputeUserMatchesJob::dispatch($user->id);
                }
            });
    }
}
