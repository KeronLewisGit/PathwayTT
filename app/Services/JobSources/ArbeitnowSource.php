<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;

/**
 * Arbeitnow — Germany-centric job board with a free public API.
 *
 *  Base URL     https://www.arbeitnow.com/api/job-board-api   (?page=N; links.next for pagination)
 *  Auth         none
 *  Rate limit   "please do not abuse"; jobs update hourly
 *               (config min_interval_minutes = 60, max_pages = 3)
 *  Attribution  "I would appreciate linking back to the site"; by using the
 *               API you agree to arbeitnow.com's terms of service. Terms
 *               ship in every response under meta.terms.
 *  Verified     2026-09-02 (live call)
 *  Caveat       Almost all listings are on-site in Germany; only remote=true
 *               rows are imported, and those are usually EU-only and often
 *               in German — hence disabled by default.
 *
 *  Fields used  slug, company_name, title, description (HTML), remote,
 *               url, tags[], job_types[], location, created_at (unix)
 */
class ArbeitnowSource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'arbeitnow';
    }

    protected function pages(): iterable
    {
        $url = config('jobsources.remote.boards.arbeitnow.url');
        $maxPages = (int) config('jobsources.remote.boards.arbeitnow.max_pages', 3);

        for ($page = 1; $page <= $maxPages; $page++) {
            $json = $this->getJson($url, ['page' => $page]);

            yield $json['data'] ?? [];

            if (empty($json['links']['next'])) {
                break;
            }
        }
    }

    protected function map(array $raw): ?JobDto
    {
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '' || empty($raw['slug']) || empty($raw['remote'])) {
            return null; // on-site roles are of no use to a T&T job seeker
        }

        $tags = (array) ($raw['tags'] ?? []);
        $description = self::text($raw['description'] ?? null);
        $skills = $this->skillsFrom($title, $tags, $description);
        $location = trim((string) ($raw['location'] ?? ''));
        $types = (array) ($raw['job_types'] ?? []);

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $raw['slug'],
            title: $title,
            companyName: self::text($raw['company_name'] ?? null, 190),
            industrySlug: self::industryFor($tags),
            workArrangement: 'remote_international',
            employmentType: self::employmentType($types[0] ?? null),
            locationText: 'Remote — '.($location !== '' ? "{$location}, Germany" : 'Germany-based employer'),
            country: null,
            isOpenToCaribbean: null, // board doesn't say; treated as "not stated"
            geoEligibility: 'region_restricted',
            seniority: self::seniority([], $title),
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::fromUnix($raw['created_at'] ?? null),
            applyUrl: $raw['url'] ?? null,
            rawPayload: [
                'tags' => $tags,
                'job_types' => $types,
                'location' => $location,
            ],
        );
    }
}
