<?php

namespace App\Services\Matching;

use App\Models\JobListing;

interface MatchScorerInterface
{
    /**
     * Score one listing for one candidate. Hard filters (geo, closed,
     * work permit) return an ineligible result rather than a low score.
     */
    public function score(CandidateProfile $candidate, JobListing $job): MatchResult;
}
