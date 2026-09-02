<?php

/*
|--------------------------------------------------------------------------
| Match scoring configuration
|--------------------------------------------------------------------------
| These are the DEFAULTS. Admin-edited overrides live in the `settings`
| table and win over these values at runtime (see SettingsService and the
| Filament "Matching & FX" page). Weights must sum to 100; the scorer
| normalizes over the applicable components so a broken override still
| yields a 0–100 score.
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
    // high match for a job they cannot do. [missing count => cap]; the
    // largest threshold <= the missing count applies.
    'required_skill_caps' => [
        1 => 80, // missing exactly 1 required skill -> score capped at 80
        2 => 60, // missing 2 or more               -> score capped at 60
    ],

    // Confidence caps: when there is no skills evidence on one side, the
    // renormalised score would otherwise rest on location/arrangement alone
    // and read as a "100% match". Null disables a cap.
    'confidence_caps' => [
        'listing_without_skills' => 60,   // listing states no skills we recognise
        'candidate_without_skills' => 40, // profile has no skills yet
    ],

    // Years of experience implied by a seniority label when a listing does
    // not state min_years_experience explicitly.
    'seniority_years' => [
        'entry' => 0,
        'mid' => 2,
        'senior' => 5,
        'manager' => 7,
    ],

    // Timezone feasibility for remote roles from AST (UTC-4):
    // [max required overlap hours => component score]. Overlap up to a
    // normal working day is easy; beyond ~8h means night shifts.
    'overlap_feasibility' => [
        6 => 100,
        8 => 70,
        24 => 40,
    ],

    // Geo component score for remote roles that don't state whether they
    // hire from the Caribbean (eligible, but the user should check).
    'unclear_geo_score' => 70,

    // Recompute batching (1k-10k users: chunk match recomputes in queue jobs).
    'recompute_chunk_size' => 100,
];
