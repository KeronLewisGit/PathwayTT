<?php

namespace App\Services\JobSources;

/**
 * Local Trinidad & Tobago job boards — STUB, deliberately unimplemented.
 *
 * None of the local boards checked expose a public, documented API:
 *
 *  - CaribbeanJobs.com (caribbeanjobs.com) — no API; robots.txt and terms
 *    prohibit automated extraction of listings.
 *  - JobsTT (jobstt.com) — no API; terms of use restrict scraping.
 *  - Ministry of Labour / National Employment Service — listings are
 *    published as web pages/PDF notices only; no feed.
 *  - Employer career pages — per-employer HTML, no common format.
 *  - LinkedIn — the Jobs API is partner-only; scraping breaches its terms.
 *
 * Per docs/SPEC.md, no scraper is written without the product owner's
 * explicit decision per source (terms of service and robots.txt review).
 * Until then, local jobs enter through ManualSource (Filament) and
 * CsvImportSource — the intended launch path.
 *
 * To implement a source later: extend RemoteBoardSource (or implement
 * JobSourceInterface directly), set key() to the board slug, add it to
 * config/jobsources.php 'sources' + 'labels', and document the board's
 * terms, rate limit and attribution in the class docblock like the
 * remote adapters do.
 */
class LocalBoardSource implements JobSourceInterface
{
    public function key(): string
    {
        return 'local_boards';
    }

    public function enabled(): bool
    {
        return false; // nothing to run; see class docblock
    }

    public function fetch(): iterable
    {
        return [];
    }

    public function notes(): ?string
    {
        return 'Stub: local T&T boards have no public API. Enter local jobs manually or via CSV.';
    }
}
