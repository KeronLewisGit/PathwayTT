<?php

/*
|--------------------------------------------------------------------------
| Advisory / Skills Gap Plan configuration
|--------------------------------------------------------------------------
| Tunables for App\Services\Advisory\SkillGapAnalyzer. Every projection
| shown to the user (score lift, jobs unlocked) is computed by re-scoring
| real listings — nothing here invents a number, these only bound the work
| and translate credential types into effort estimates.
*/

return [

    // Build the plan inside the page request (instant; right for shared hosting where
    // cron drains the queue once a minute). false = queue GenerateSkillGapPlanJob.
    'generate_inline' => (bool) env('ADVISORY_GENERATE_INLINE', true),

    // How many candidate gaps get the full what-if re-scoring.
    'max_gaps' => 12,

    // Most-recent open listings considered (bounds work on shared hosting).
    'listing_cap' => 400,

    // If preference filters leave fewer listings than this, widen to all.
    'min_scope_listings' => 5,

    // Impact points awarded per listing that crosses the advisory threshold,
    // on top of the raw score lift. Keeps "unlocks a job" ahead of "+3 on
    // ten jobs you still couldn't get".
    'unlock_bonus' => 25,

    // Plan phases by estimated effort (weeks).
    'phase_windows' => [
        'quick' => 4,   // Quick wins: <= 4 weeks
        'core' => 26,   // Core credential: 1–6 months
        // anything longer → Long-term
    ],

    // Effort estimate when a resource has no duration_weeks. Labelled as an
    // estimate in the UI ("confirm with provider").
    'effort_by_credential' => [
        'badge' => 2,
        'certificate' => 8,
        'professional' => 26,
        'diploma' => 40,
        'degree' => 104,
    ],

    // Effort when a gap has no catalogued resource at all.
    'unknown_effort_weeks' => 12,

    // Resources shown per track (local / online) per gap.
    'resources_per_track' => 4,

    // Evidence in the profile that the user already presents as remote-ready.
    'remote_signal_pattern' => '/\b(remote|github|portfolio|wise|payoneer|distributed|freelanc\w*|upwork|fiverr|async)\b/i',
];
