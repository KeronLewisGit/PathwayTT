<?php

/*
|--------------------------------------------------------------------------
| Match scoring configuration
|--------------------------------------------------------------------------
| These are the DEFAULTS. Admin-edited overrides live in the `settings`
| table and win over these values at runtime (see SettingsService).
| Weights must sum to 100; MatchScoringService normalizes defensively
| if an admin override breaks that invariant.
*/

return [

    // Component weights for the 0-100 match score.
    'weights' => [
        'required_skills' => 35, // Required skills coverage
        'bonus_skills'    => 10, // Preferred/bonus skills coverage
        'experience'      => 15, // Years of experience vs required
        'education'       => 10, // Education / qualification level met
        'industry'        => 10, // Industry alignment
        'arrangement'     => 10, // Work arrangement match (user pref vs job)
        'geo_timezone'    => 10, // Geo eligibility + timezone overlap feasibility
    ],

    // Below this best-match score, the UI pivots to the Skills Gap Plan.
    'advisory_threshold' => 55,

    // Missing required skills cap the total score so a user never sees a
    // high match for a job they cannot do.
    'required_skill_caps' => [
        1 => 80, // missing exactly 1 required skill -> score capped at 80
        2 => 60, // missing 2 or more               -> score capped at 60
    ],

    // Recompute batching (1k-10k users: chunk match recomputes in queue jobs).
    'recompute_chunk_size' => 100,
];
