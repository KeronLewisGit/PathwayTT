<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Services\JobSources\Support\GeoInference;

/**
 * Remotive — remote jobs, mostly tech, worldwide.
 *
 *  Base URL     https://remotive.com/api/remote-jobs   (optional ?limit=N, ?category=)
 *  Auth         none
 *  Rate limit   ≤ 4 requests/day recommended; > 2 requests/minute is blocked
 *               (config min_interval_minutes = 360)
 *  Attribution  MUST link back to the Remotive job URL AND name Remotive as
 *               the source, or API access is terminated. Listings are
 *               delayed 24h by Remotive. Must NOT be re-posted to other job
 *               boards (Jooble, LinkedIn Jobs, Google Jobs…). The legal notice
 *               ships in every response under "0-legal-notice".
 *  Verified     2026-09-02 (live call + github.com/remotive-com/remote-jobs-api)
 *
 *  Fields used  id, url, title, company_name, category, tags[], job_type,
 *               publication_date, candidate_required_location, description (HTML)
 */
class RemotiveSource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'remotive';
    }

    protected function pages(): iterable
    {
        $json = $this->getJson(config('jobsources.remote.boards.remotive.url'), [
            'limit' => (int) config('jobsources.remote.max_per_source', 500),
        ]);

        yield $json['jobs'] ?? [];
    }

    protected function map(array $raw): ?JobDto
    {
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '' || empty($raw['id'])) {
            return null;
        }

        $location = trim((string) ($raw['candidate_required_location'] ?? ''));
        $geo = GeoInference::infer($location);
        $description = self::text($raw['description'] ?? null);
        $skills = $this->skillsFrom($title, (array) ($raw['tags'] ?? []), $description);

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $raw['id'],
            title: $title,
            companyName: self::text($raw['company_name'] ?? null, 190),
            industrySlug: self::industryFor([(string) ($raw['category'] ?? '')]),
            workArrangement: 'remote_international',
            employmentType: self::employmentType($raw['job_type'] ?? null),
            locationText: $location !== '' ? "Remote — {$location}" : 'Remote',
            country: $geo['country'],
            isOpenToCaribbean: $geo['open'],
            geoEligibility: $geo['geo'],
            seniority: self::seniority([], $title),
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::iso($raw['publication_date'] ?? null),
            applyUrl: $raw['url'] ?? null,
            rawPayload: [
                'category' => $raw['category'] ?? null,
                'job_type' => $raw['job_type'] ?? null,
                'candidate_required_location' => $location,
                'salary' => $raw['salary'] ?? null, // free text; not parsed
                'tags' => $raw['tags'] ?? [],
            ],
        );
    }
}
