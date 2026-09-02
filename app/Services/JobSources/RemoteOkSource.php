<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Services\JobSources\Support\GeoInference;

/**
 * Remote OK — remote (and, increasingly, on-site) listings; ~100 latest.
 *
 *  Base URL     https://remoteok.com/api
 *  Auth         none
 *  Rate limit   none published (config min_interval_minutes = 60)
 *  Attribution  MUST link back to the Remote OK URL with a FOLLOW link (no
 *               rel="nofollow") and name "Remote OK" as the source, or API
 *               access is suspended. The Remote OK logo may not be used
 *               without written permission. Terms ship as the first array
 *               element of every response ("legal").
 *  Verified     2026-09-02 (live call)
 *  Caveat       "location" is a city/region hint, not an applicant
 *               restriction, and the feed contains on-site roles labelled
 *               remote — hence disabled by default in config/jobsources.php.
 *
 *  Fields used  id, position, company, tags[], description (HTML), location,
 *               apply_url / url, salary_min, salary_max (annual USD, 0 = none), date
 */
class RemoteOkSource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'remoteok';
    }

    protected function pages(): iterable
    {
        $json = $this->getJson(config('jobsources.remote.boards.remoteok.url'));

        // Element 0 is the legal notice, not a job.
        yield array_values(array_filter($json, fn ($row) => is_array($row) && ! empty($row['id'])));
    }

    protected function map(array $raw): ?JobDto
    {
        $title = trim(html_entity_decode((string) ($raw['position'] ?? ''), ENT_QUOTES | ENT_HTML5));
        if ($title === '' || empty($raw['id'])) {
            return null;
        }

        $location = trim((string) ($raw['location'] ?? ''), " ,\t");
        $geo = GeoInference::infer($location);
        $description = self::text($raw['description'] ?? null);
        $tags = (array) ($raw['tags'] ?? []);
        $skills = $this->skillsFrom($title, $tags, $description);

        $min = self::cents($raw['salary_min'] ?? null);
        $max = self::cents($raw['salary_max'] ?? null);

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $raw['id'],
            title: $title,
            companyName: self::text($raw['company'] ?? null, 190),
            industrySlug: self::industryFor($tags),
            workArrangement: 'remote_international',
            employmentType: null,
            locationText: $location !== '' ? "Remote — {$location}" : 'Remote',
            country: $geo['country'],
            isOpenToCaribbean: $geo['open'],
            geoEligibility: $geo['geo'],
            seniority: self::seniority([], $title),
            salaryMinCents: $min,
            salaryMaxCents: $max,
            salaryCurrency: ($min || $max) ? 'USD' : null,
            salaryPeriod: ($min || $max) ? 'yearly' : null,
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::iso($raw['date'] ?? null),
            applyUrl: $raw['apply_url'] ?? ($raw['url'] ?? null),
            rawPayload: [
                'tags' => $tags,
                'location' => $location,
                'slug' => $raw['slug'] ?? null,
            ],
        );
    }
}
