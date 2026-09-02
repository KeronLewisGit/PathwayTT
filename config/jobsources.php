<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Job source adapters
    |--------------------------------------------------------------------------
    | Classes implementing JobSourceInterface, run in order by job:sync.
    | Remote API adapters (Remotive, RemoteOK, …) are added here in Phase 6
    | after their endpoints and terms are verified.
    */

    'sources' => [
        App\Services\JobSources\ManualSource::class,
        App\Services\JobSources\CsvImportSource::class,
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
];
