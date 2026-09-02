<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
| Feature tests get the Laravel TestCase and a fresh database per test.
| Tests\TestCase guarantees that database is in-memory sqlite regardless of
| the surrounding environment (see its createApplication / setUpTraits).
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
