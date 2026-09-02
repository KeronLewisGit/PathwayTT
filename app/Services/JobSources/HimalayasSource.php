<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Services\JobSources\Support\GeoInference;

/**
 * Himalayas — large remote-job feed with explicit location AND timezone
 * restrictions per listing (the most precise geo data of the boards).
 *
 *  Base URL     https://himalayas.app/jobs/api   (?limit=N, ?cursor= from nextCursor; ?offset= deprecated)
 *  Auth         none
 *  Rate limit   none published; we page politely and cache for an hour
 *               (config min_interval_minutes = 60)
 *  Attribution  each listing carries "Originally posted on Himalayas"; we
 *               name Himalayas as the source and link applicationLink.
 *  Verified     2026-09-02 (live call; response "comments" field documents
 *               cursor pagination, added 2026-08-21)
 *
 *  Fields used  guid, title, companyName, employmentType, seniority[],
 *               minSalary, maxSalary, currency, salaryPeriod,
 *               locationRestrictions[] (country names; [] = anywhere),
 *               timezoneRestrictions[] (allowed UTC offsets; [] = any),
 *               categories[], description (HTML), pubDate, expiryDate
 *               (unix seconds), applicationLink
 */
class HimalayasSource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'himalayas';
    }

    protected function pages(): iterable
    {
        $url = config('jobsources.remote.boards.himalayas.url');
        $pageSize = (int) config('jobsources.remote.boards.himalayas.page_size', 100);
        $max = (int) config('jobsources.remote.max_per_source', 500);
        $cursor = null;
        $seen = 0;

        do {
            $json = $this->getJson($url, array_filter(['limit' => $pageSize, 'cursor' => $cursor]));
            $jobs = $json['jobs'] ?? [];
            $seen += count($jobs);

            yield $jobs;

            $cursor = $json['nextCursor'] ?? null;
        } while ($cursor && $jobs !== [] && $seen < $max);
    }

    protected function map(array $raw): ?JobDto
    {
        $title = trim((string) ($raw['title'] ?? ''));
        $link = $raw['applicationLink'] ?? ($raw['guid'] ?? null);
        if ($title === '' || ! $link) {
            return null;
        }

        $locations = array_values(array_filter((array) ($raw['locationRestrictions'] ?? [])));
        $geo = $locations === []
            ? ['geo' => 'worldwide', 'country' => null, 'open' => true]
            : GeoInference::infer($locations);

        // Timezone windows: T&T is UTC-4. A listing that lists offsets but
        // not -4 is effectively closed to T&T residents.
        $tzAllows = GeoInference::timezoneAllowsAst((array) ($raw['timezoneRestrictions'] ?? []));
        if ($tzAllows === false) {
            $geo = ['geo' => 'region_restricted', 'country' => $geo['country'], 'open' => false];
        }

        $categories = array_map(fn ($c) => str_replace('-', ' ', (string) $c), (array) ($raw['categories'] ?? []));
        $description = self::text($raw['description'] ?? ($raw['excerpt'] ?? null));
        $skills = $this->skillsFrom($title, $categories, $description);

        $currency = strtoupper(trim((string) ($raw['currency'] ?? '')));
        $min = self::cents($raw['minSalary'] ?? null);
        $max = self::cents($raw['maxSalary'] ?? null);

        $locationText = $locations === [] ? 'Remote — Worldwide' : 'Remote — '.implode(', ', $locations);
        if ($tzAllows === false) {
            $locationText .= ' (timezone-restricted)';
        }

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) ($raw['guid'] ?? $link),
            title: $title,
            companyName: self::text($raw['companyName'] ?? null, 190),
            industrySlug: self::industryFor(array_merge($categories, (array) ($raw['parentCategories'] ?? []))),
            workArrangement: 'remote_international',
            employmentType: self::employmentType($raw['employmentType'] ?? null),
            locationText: $locationText,
            country: $geo['country'],
            isOpenToCaribbean: $geo['open'],
            geoEligibility: $geo['geo'],
            seniority: self::seniority((array) ($raw['seniority'] ?? []), $title),
            salaryMinCents: $min,
            salaryMaxCents: $max,
            salaryCurrency: ($min || $max) && $currency !== '' ? $currency : null,
            salaryPeriod: ($min || $max) ? self::period($raw['salaryPeriod'] ?? null) : null,
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::fromUnix($raw['pubDate'] ?? null),
            closesAt: self::fromUnix($raw['expiryDate'] ?? null),
            applyUrl: $link,
            rawPayload: [
                'locationRestrictions' => $locations,
                'timezoneRestrictions' => $raw['timezoneRestrictions'] ?? [],
                'seniority' => $raw['seniority'] ?? [],
                'categories' => $raw['categories'] ?? [],
                'employmentType' => $raw['employmentType'] ?? null,
            ],
        );
    }
}
