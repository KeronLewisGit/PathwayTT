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
        // Local T&T boards (HTML crawlers, daily; see docs/LOCAL-BOARDS.md)
        App\Services\JobSources\CaribbeanJobsSource::class,
        App\Services\JobSources\TrinidadJobSource::class,
        App\Services\JobSources\JobsTtSource::class,
        App\Services\JobSources\EmployTtSource::class,
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
        'caribbeanjobs' => ['label' => 'CaribbeanJobs.com', 'url' => 'https://www.caribbeanjobs.com'],
        'trinidadjob' => ['label' => 'TrinidadJob.com', 'url' => 'https://trinidadjob.com'],
        'jobstt' => ['label' => 'JobsTT', 'url' => 'https://www.jobstt.com'],
        'employtt' => ['label' => 'EmployTT (Government of T&T)', 'url' => 'https://employtt.gov.tt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry from job title (local boards)
    |--------------------------------------------------------------------------
    | CaribbeanJobs cards carry no category, so without this every local
    | listing had industry NULL and vanished the moment a user picked an
    | industry filter. Ordered, word-bounded patterns run over the title first
    | and then title + excerpt; the first hit wins. Keep specific sectors above
    | generic words ("server" is ICT before it is hospitality). A miss leaves
    | the industry unset rather than guessing.
    */

    'title_industry_patterns' => [
        ['public-sector', '/\b(ministry|government|public service|municipal|regional corporation|statutory|police|defence|customs officer|immigration)\b/'],
        ['ict-software', '/\b(software|developer|programmer|devops|data (?:analyst|scientist|engineer)|database|network|system\w* (?:administrator|analyst|engineer|support)|server (?:administrator|engineer)|it (?:support|officer|technician|manager|audit\w*)|information technology|cyber\w*|cloud|web|front-?end|back-?end|full-?stack|platform engineer\w*|sre|qa engineer|tester|ict|help ?desk technician|erp|sap|business (?:analyst|intelligence)|scrum|product manager|ui|ux)\b/'],
        ['healthcare', '/\b(nurse|nursing|medical|clinic\w*|pharmac\w*|dental|dentist|physician|doctor|health|caregiver|lab technologist|phlebotom\w*|radiograph\w*|physiotherap\w*|optometr\w*|locum|therapist|dietitian)\b/'],
        ['education', '/\b(teacher|teaching|tutor|lecturer|instructor|educat\w*|school|curriculum|trainer)\b/'],
        ['energy-petrochemicals', '/\b(petrochem\w*|oil|gas|refiner\w*|drilling|offshore|onshore|rig|pipeline|wellsite|well site|petroleum|lng|upstream|downstream|hse|energy|instrumentation|api inspector|coatings? inspector|corrosion|ndt)\b/'],
        ['construction', '/\b(construction|civil|carpenter|mason|electrician|plumber|welder|pipefitter|site supervisor|quantity surveyor|surveyor|architect\w*|hvac|scaffold\w*|crane|foreman|estimator|draught\w*|drafts\w*|project engineer|facilit\w*)\b/'],
        ['logistics-shipping', '/\b(logistic\w*|supply chain|shipping|warehouse|freight|courier|driver|dispatch\w*|fleet|forklift|port operations|customs broker\w*|inventory|commissary|porter)\b/'],
        ['bpo-contact-centre', '/\b(call cent\w*|contact cent\w*|customer (?:service|support|care|experience)|telemarket\w*|tele-?(?:sales|collector)|help ?desk|bpo)\b/'],
        ['tourism-hospitality', '/\b(hotel|resort|restaurant|barista|chef|cook|kitchen|waiter|waitress|bartender|mixologist|bar|housekeep\w*|front desk|concierge|hospitality|tour\w*|travel|guest services|steward\w*|food (?:and|&) beverage|f&b|catering|duty free)\b/'],
        ['professional-services-accountinglegalconsulting', '/\b(accountant|accounting|accounts|audit\w*|bookkeep\w*|legal|attorney|lawyer|paralegal|counsel|human resource\w*|hr|recruit\w*|consultant|consulting|payroll|compliance|administrative|administrator|admin|administration|secretary|receptionist|clerk|office assistant|executive assistant|procurement|purchasing|project manager|operations manager)\b/'],
        ['financial-services-insurance', '/\b(bank\w*|insurance|underwrit\w*|actuar\w*|credit|loan\w*|teller|financial (?:advis\w*|analyst|officer)|investment|treasury|claims|collections?|collector|settlement|billing|reconciliation|risk analyst|fp&a|finance)\b/'],
        ['creative-media', '/\b(graphic|design\w*|writer|copywrit\w*|content|creative|marketing|media|video|photograph\w*|brand\w*|social media|communications officer|public relations|journalist|editor)\b/'],
        ['distribution-retail', '/\b(sales|salesman|merchandis\w*|cashier|retail|store|shop|showroom|business development|account executive|distribution|wholesale|buyer|tell sell)\b/'],
        ['manufacturing', '/\b(manufactur\w*|production|machine operator|machinist|factory|plant|assembly|quality (?:control|assurance)|qc|maintenance technician|mechanic\w*|technician|fabricat\w*|packag\w*|line operator|process operator|product development)\b/'],
        ['agriculture-agro-processing', '/\b(agricultur\w*|farm\w*|agro\w*|crop|livestock|veterinar\w*|fisher\w*|estate)\b/'],
        // Last resort: generic office/management titles that say nothing about a
        // sector ("Country Manager", "Operations Assistant") file under professional
        // services rather than vanishing from every industry-filtered view.
        ['professional-services-accountinglegalconsulting', '/\b(manager|officer|assistant|coordinator|supervisor|lead|analyst|associate|representative|specialist|executive|operations)\b/'],
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

            // ── Local T&T boards: HTML crawlers, once a day ──────────
            // robots.txt is checked before every fetch; see each adapter's
            // docblock and docs/LOCAL-BOARDS.md for the terms read on
            // 2026-09-03 and why two of the three are off by default.
            'caribbeanjobs' => [
                'enabled' => (bool) env('JOBSOURCE_CARIBBEANJOBS', true),
                'url' => 'https://www.caribbeanjobs.com/ShowResults.aspx?Location=124',
                'max_pages' => (int) env('JOBSOURCE_CARIBBEANJOBS_PAGES', 4), // 25 listings per page
                'min_interval_minutes' => 1440,
                'delay_ms' => 1500,
            ],
            // TrinidadJob.com publishes its listings through the standard public
            // WordPress REST API (no terms of use exist; robots.txt allows all —
            // read 2026-09-07). Polled twice a day at most.
            'trinidadjob' => [
                'enabled' => (bool) env('JOBSOURCE_TRINIDADJOB', true),
                'url' => 'https://trinidadjob.com/wp-json/wp/v2/job-listings',
                'per_page' => 100,
                'max_pages' => (int) env('JOBSOURCE_TRINIDADJOB_PAGES', 2),
                'min_interval_minutes' => 720,
            ],
            'jobstt' => [
                'enabled' => (bool) env('JOBSOURCE_JOBSTT', false), // terms forbid robots/aggregation — permission needed
                'url' => 'https://www.jobstt.com/job',
                'max_pages' => (int) env('JOBSOURCE_JOBSTT_PAGES', 3),
                'fetch_details' => true,
                'min_interval_minutes' => 1440,
                'delay_ms' => 1500,
            ],
            'employtt' => [
                'enabled' => (bool) env('JOBSOURCE_EMPLOYTT', false), // terms require iGovTT's written permission
                'url' => 'https://employtt.gov.tt/jobs/list',
                'fetch_details' => true,
                'min_interval_minutes' => 1440,
                'delay_ms' => 1500,
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
