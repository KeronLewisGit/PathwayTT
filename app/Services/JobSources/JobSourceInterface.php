<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;

interface JobSourceInterface
{
    /** Unique source key stored on job_listings.source (e.g. "csv"). */
    public function key(): string;

    /** Whether this source should run during job:sync. */
    public function enabled(): bool;

    /** @return iterable<JobDto> */
    public function fetch(): iterable;

    /** Human-readable note about the last fetch (throttled, skipped counts…), shown on the sync dashboard. */
    public function notes(): ?string;
}
