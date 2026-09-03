<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Job source adapters
    |--------------------------------------------------------------------------
    | Classes implementing JobSourceInterface, run in order by job:sync.
    | Remote boards are individually switchable below (env JOBSOURCE_*).
    */

    'sources' => [
        App\Services\JobSources\ManualSource::class,
        App\Services\JobSources\CsvImportSource::class,
        App\Services\JobSources\RemotiveSource::class,
        App\Services\JobSources\JobicySource::class,
        App\Services\JobSources\HimalayasSource::class,
        App\Services\JobSources\RemoteOkSource::class,
        App\Services\JobSources\ArbeitnowSource::class,
        App\Services\JobSources\LocalBoardSource::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Source attribution
    |--------------------------------------------------------------------------
    | Shown wherever a listing's source appears. Several boards make naming
    | them and linking to the original posting a condition of API access.
    */

    'labels' => [
        'manual' => ['label' => 'PathwayTT (entered by our team)', 'url' => null],
        'csv' => ['label' => 'PathwayTT (imported)', 'url' => null],
        'demo' => ['label' => 'Demo data', 'url' => null],
        'remotive' => ['label' => 'Remotive', 'url' => 'https://remotive.com'],
        'jobicy' => ['label' => 'Jobicy', 'url' => 'https://jobicy.com'],
        'himalayas' => ['label' => 'Himalayas', 'url' => 'https://himalayas.app'],
        'remoteok' => ['label' => 'Remote OK', 'url' => 'https://remoteok.com'],
        'arbeitnow' => ['label' => 'Arbeitnow', 'url' => 'https://www.arbeitnow.com'],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV import
    |--------------------------------------------------------------------------
    | Drop CSV files matching the template (docs/job-import-template.csv)
    | into the inbox directory (relative to the private storage disk);
    | job:sync ingests then archives them. Admins can also upload through
    | Filament, which writes into the same inbox.
    */

    'csv' => [
        'disk' => 'local',
        'inbox' => 'import/jobs',
        'archive' => 'import/jobs/processed',
        'failed' => 'import/jobs/failed',
    ],

    /*
    |--------------------------------------------------------------------------
    | Remote job boards (public, free APIs — verified live 2026-09-02)
    |--------------------------------------------------------------------------
    | Each adapter's class docblock records the board's base URL, auth,
    | rate limit and attribution terms. min_interval_minutes keeps manual
    | "Run sync now" clicks inside those limits; the nightly schedule is
    | well within all of them.
    |
    | import_ineligible=false skips listings a T&T resident cannot apply to
    | (US-only etc.) at ingest, so the table only holds jobs worth scoring.
    */

    /*
    |--------------------------------------------------------------------------
    | Live feed behaviour
    |--------------------------------------------------------------------------
    | show_demo_listings: include the [DEMO] listings in the public feed and
    | matching (off = real sources only; demo data stays for tests/dev).
    | auto_refresh_minutes: when someone opens the Jobs page and the last
    | successful board fetch is older than this, a sync is queued (each board
    | still honours its own rate-limit window).
    */

    'show_demo_listings' => (bool) env('JOBSOURCE_SHOW_DEMO', false),
    'auto_refresh_minutes' => (int) env('JOBSOURCE_AUTO_REFRESH_MINUTES', 60),

    'remote' => [
        'user_agent' => env('JOBSOURCE_USER_AGENT', 'PathwayTT job matching for Trinidad & Tobago (+https://pathwaytt.test)'),
        'timeout' => 30,
        'max_per_source' => (int) env('JOBSOURCE_MAX_PER_SOURCE', 500),
        'import_ineligible' => (bool) env('JOBSOURCE_IMPORT_INELIGIBLE', false),

        'boards' => [
            'remotive' => [
                'enabled' => (bool) env('JOBSOURCE_REMOTIVE', true),
                'url' => 'https://remotive.com/api/remote-jobs',
                'min_interval_minutes' => 360, // board asks for ≤ 4 requests/day
            ],
            'jobicy' => [
                'enabled' => (bool) env('JOBSOURCE_JOBICY', true),
                'url' => 'https://jobicy.com/api/v2/remote-jobs',
                'count' => 200, // API maximum per request
                'min_interval_minutes' => 60, // "once or twice per hour"
            ],
            'himalayas' => [
                'enabled' => (bool) env('JOBSOURCE_HIMALAYAS', true),
                'url' => 'https://himalayas.app/jobs/api',
                'page_size' => 100,
                'min_interval_minutes' => 60,
            ],
            // Off by default: the feed mixes on-site roles into "remote" and
            // rarely states who may apply. Enable once you've reviewed a run.
            'remoteok' => [
                'enabled' => (bool) env('JOBSOURCE_REMOTEOK', false),
                'url' => 'https://remoteok.com/api',
                'min_interval_minutes' => 60,
            ],
            // Off by default: Germany-centric board; remote roles are mostly
            // EU-only and often in German. Kept for completeness.
            'arbeitnow' => [
                'enabled' => (bool) env('JOBSOURCE_ARBEITNOW', false),
                'url' => 'https://www.arbeitnow.com/api/job-board-api',
                'max_pages' => 3,
                'min_interval_minutes' => 60,
            ],
        ],

        // Board category / industry strings → our industry slug. First
        // matching keyword wins, so more specific groups come first.
        'industry_keywords' => [
            'healthcare' => ['health', 'medical', 'nurs', 'clinical', 'pharma', 'care'],
            'education' => ['teach', 'education', 'tutor', 'instruct', 'learning'],
            'bpo-contact-centre' => ['customer', 'support', 'call cent', 'success', 'help desk', 'helpdesk'],
            'logistics-shipping' => ['logistic', 'supply chain', 'shipping', 'warehouse'],
            'financial-services-insurance' => ['financ', 'bank', 'insur', 'fintech', 'payments'],
            'professional-services-accountinglegalconsulting' => ['account', 'legal', 'law', 'human resource', 'hr', 'recruit', 'consult', 'admin', 'operations', 'project management'],
            'creative-media' => ['design', 'writ', 'content', 'creative', 'marketing', 'media', 'video', 'brand', 'social'],
            'distribution-retail' => ['sales', 'business development', 'ecommerce', 'e-commerce', 'retail', 'account executive'],
            'ict-software' => ['software', 'engineer', 'develop', 'devops', 'data', 'qa', 'product', 'security', 'cloud', 'tech', 'programm', 'it', 'ai', 'machine learning', 'blockchain', 'stem'],
        ],
    ],
];
