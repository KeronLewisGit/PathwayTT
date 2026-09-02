<?php

namespace App\Services\JobSources;

/**
 * Manual entry source. Admin-entered jobs are written straight to
 * job_listings by the Filament resource with source = "manual", so this
 * adapter has nothing to fetch — it exists so "manual" is a first-class,
 * always-available source in the sync pipeline and its runs are logged.
 */
class ManualSource implements JobSourceInterface
{
    public function key(): string
    {
        return 'manual';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function fetch(): iterable
    {
        return [];
    }

    public function notes(): ?string
    {
        return null;
    }
}
