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

    // Score matches inside the Matches page request on first visit and on "Recompute"
    // (instant; right for shared hosting where cron drains the queue once a minute).
    // false = queue RecomputeUserMatchesJob and poll.
    'recompute_inline' => (bool) env('MATCHING_RECOMPUTE_INLINE', true),

    // Component weights for the 0-100 match score.
    'weights' => [
        'required_skills' => 30, // Required skills coverage
        'bonus_skills'    => 10, // Preferred/bonus skills coverage (absorbs the required weight when a listing states only nice-to-haves)
        'role_fit'        => 15, // Listing title vs the roles on the resume (and the field they imply)
        'experience'      => 10, // Years of experience vs required
        'education'       => 10, // Education / qualification level met
        'industry'        => 10, // Industry alignment (preference, else the field the resume implies)
        'arrangement'     => 10, // Work arrangement match (user pref vs job)
        'geo_timezone'    => 5,  // Geo eligibility + timezone overlap feasibility
    ],

    /*
    |--------------------------------------------------------------------------
    | Skill evidence
    |--------------------------------------------------------------------------
    | Skills in these categories (teamwork, communication, remote-work habits,
    | languages) are on almost every profile and every listing; sharing only
    | those is not evidence that someone can do the job.
    */
    'generic_skill_categories' => ['soft-skills', 'remote-work', 'languages'],

    // When a listing states skills we recognise, the score is capped by how
    // many of them the candidate shares. Without this a listing whose only
    // stated skills are all missing still scored 70+ on location/experience.
    'skill_overlap_caps' => [
        'none' => 45, // shares none of the listing's non-generic skills
        'low' => 60,  // shares fewer than `low_overlap_ratio` of all stated skills
    ],
    'low_overlap_ratio' => 0.34,

    // Dominant skill category on a profile → the industry the resume implies.
    // Used for industry alignment when the user has not set a target industry,
    // and as the weaker half of role fit. Needs a real cluster of specific
    // skills before it says anything (one skill is not a field).
    'min_skills_for_inference' => 3,
    'skill_category_industries' => [
        'software-it' => 'ict-software',
        'finance-accounting' => 'financial-services-insurance',
        'trades-energy' => 'energy-petrochemicals',
        'sales-marketing' => 'distribution-retail',
        'office-admin' => 'professional-services-accountinglegalconsulting',
        'professional-services' => 'professional-services-accountinglegalconsulting',
        'healthcare' => 'healthcare',
        'hospitality-tourism' => 'tourism-hospitality',
        'logistics-shipping' => 'logistics-shipping',
        'creative-media' => 'creative-media',
        'construction' => 'construction',
        'agriculture' => 'agriculture-agro-processing',
        'bpo-contact-centre' => 'bpo-contact-centre',
        'education' => 'education',
    ],

    /*
    |--------------------------------------------------------------------------
    | Role vocabulary (see App\Services\Matching\RoleVocabulary)
    |--------------------------------------------------------------------------
    */
    // Multi-word phrases collapsed before tokenising: regex => canonical term.
    'role_phrases' => [
        '/\b(software|front-?end|back-?end|full-?stack|web|platform|devops|cloud|data|systems?|application|mobile|ai|ml) engineer\w*/' => 'developer',
        '/\b(help ?desk|it support|technical support|desktop support|service desk|systems? support|it solutions?)\b/' => 'itsupport',
        '/\bbusiness development\b/' => 'sales',
        '/\bsupply chain\b/' => 'logistics',
        '/\bhuman resources?\b/' => 'hr',
        '/\bcustomer (service|support|care|experience)\b/' => 'customerservice',
        '/\bcall cent(re|er)\b/' => 'customerservice',
        '/\bquantity surveyor\b/' => 'surveyor',
        '/\bfood (and|&) beverage\b/' => 'hospitality',
    ],

    // canonical term => variants (stemmed automatically, so "programmers" → programmer).
    'role_synonyms' => [
        'developer' => ['programmer', 'coder', 'development', 'programming', 'software'],
        'itsupport' => ['helpdesk', 'sysadmin'],
        'accountant' => ['accounting', 'accounts', 'bookkeeper', 'bookkeeping', 'auditor', 'audit', 'payable', 'receivable', 'payables', 'receivables'],
        'nurse' => ['nursing', 'rn'],
        'teacher' => ['tutor', 'tutoring', 'lecturer', 'instructor', 'teaching', 'educator', 'trainer'],
        'driver' => ['chauffeur', 'courier'],
        'sales' => ['salesman', 'salesperson', 'saleswoman', 'merchandiser', 'telesales'],
        'admin' => ['administrative', 'administrator', 'administration', 'secretary', 'clerk', 'receptionist', 'clerical'],
        'chef' => ['cook', 'culinary', 'kitchen', 'baker'],
        'marketing' => ['marketer', 'brand', 'advertising'],
        'hr' => ['recruiter', 'recruitment', 'recruiting', 'payroll'],
        'attorney' => ['lawyer', 'legal', 'counsel', 'paralegal', 'solicitor'],
        'security' => ['guard'],
        'logistics' => ['warehouse', 'inventory', 'dispatcher', 'freight', 'shipping'],
        'customerservice' => ['csr', 'telemarketer', 'collector', 'collections'],
        'hospitality' => ['bartender', 'barista', 'waiter', 'waitress', 'server', 'housekeeper', 'housekeeping', 'concierge'],
        'mechanic' => ['mechanical'],
        'electrician' => ['electrical'],
        'welder' => ['welding', 'fabricator', 'pipefitter'],
        'analyst' => ['analysis', 'analytics'],
        'designer' => ['design', 'graphic', 'ux', 'ui'],
        'writer' => ['copywriter', 'content', 'journalist', 'editor'],
        'finance' => ['financial', 'banking', 'bank', 'treasury', 'credit', 'loans', 'underwriter', 'insurance'],
    ],

    // Dropped before comparison: seniority, filler, employment and location words.
    'role_stopwords' => [
        'senior', 'junior', 'sr', 'jr', 'lead', 'principal', 'staff', 'chief', 'head', 'director', 'vp',
        'manager', 'management', 'officer', 'assistant', 'associate', 'coordinator', 'supervisor', 'specialist',
        'executive', 'representative', 'agent', 'intern', 'trainee', 'graduate', 'entry', 'level', 'mid',
        'and', 'the', 'for', 'with', 'from', 'into', 'per', 'via', 'our', 'your', 'you', 'all', 'new',
        'remote', 'hybrid', 'onsite', 'on-site', 'work', 'home', 'part', 'full', 'time', 'contract', 'temporary',
        'permanent', 'fixed', 'term', 'month', 'months', 'year', 'years', 'urgent', 'immediate', 'needed', 'wanted',
        'hiring', 'vacancy', 'position', 'role', 'job', 'jobs', 'direct', 'client', 'team', 'member', 'general',
        'trinidad', 'tobago', 'caribbean', 'region', 'regional', 'worldwide', 'international', 'south', 'north',
        'east', 'west', 'central', 'ltd', 'limited', 'inc', 'company', 'group', 'services', 'service', 'solutions',
        'experience', 'experienced', 'skilled', 'various', 'multiple', 'several', 'high', 'priority', 'systems',
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
