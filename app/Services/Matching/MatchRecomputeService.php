<?php

namespace App\Services\Matching;

use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds a user's job_matches against every open listing. Runs inside
 * queued jobs (RecomputeUserMatchesJob / RecomputeAllMatchesJob) so it's
 * never in the request cycle; chunked for the 1k–10k user scale.
 */
class MatchRecomputeService
{
    public function __construct(private readonly MatchScorerInterface $scorer) {}

    /** @return int number of listings scored */
    public function recomputeForUser(User $user): int
    {
        $candidate = CandidateProfile::fromUser($user);
        $scored = 0;

        JobListing::query()
            ->active()
            ->with('skills')
            ->chunkById((int) config('matching.recompute_chunk_size', 100), function (Collection $listings) use ($candidate, $user, &$scored) {
                foreach ($listings as $listing) {
                    $result = $this->scorer->score($candidate, $listing);

                    JobMatch::query()->updateOrCreate(
                        ['user_id' => $user->id, 'job_listing_id' => $listing->id],
                        $result->toAttributes(),
                    );

                    $scored++;
                }
            });

        // Listings that closed or were deactivated drop out of the match list.
        JobMatch::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave('jobListing', fn (Builder $q) => $q->active())
            ->delete();

        return $scored;
    }
}
