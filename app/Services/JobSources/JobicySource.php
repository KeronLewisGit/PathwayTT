<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Services\JobSources\Support\GeoInference;

/**
 * Jobicy — remote jobs across industries.
 *
 *  Base URL     https://jobicy.com/api/v2/remote-jobs   (?count=1..200, ?geo=, ?industry=, ?tag=)
 *  Auth         none ("No account or authentication header is required")
 *  Rate limit   "once or twice per hour" at most; cache responses
 *               (config min_interval_minutes = 60)
 *  Attribution  Credit Jobicy clearly with a direct link and keep the
 *               canonical Jobicy job URL as the apply link. Friendly notice
 *               ships in every response ("friendlyNotice").
 *  Verified     2026-09-02 (live call + jobicy.com/jobs-rss-feed docs)
 *
 *  Fields used  id, url, jobTitle, companyName, jobIndustry[], jobType[],
 *               jobGeo, jobLevel, jobDescription (HTML), pubDate,
 *               salaryMin, salaryMax, salaryCurrency, salaryPeriod
 */
class JobicySource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'jobicy';
    }

    protected function pages(): iterable
    {
        $json = $this->getJson(config('jobsources.remote.boards.jobicy.url'), [
            'count' => min((int) config('jobsources.remote.boards.jobicy.count', 200), (int) config('jobsources.remote.max_per_source', 500)),
        ]);

        yield $json['jobs'] ?? [];
    }

    protected function map(array $raw): ?JobDto
    {
        $title = trim((string) ($raw['jobTitle'] ?? ''));
        if ($title === '' || empty($raw['id'])) {
            return null;
        }

        $geoText = trim((string) ($raw['jobGeo'] ?? ''));
        $geo = GeoInference::infer($geoText);
        $description = self::text($raw['jobDescription'] ?? ($raw['jobExcerpt'] ?? null));
        $industries = (array) ($raw['jobIndustry'] ?? []);
        $skills = $this->skillsFrom($title, $industries, $description);
        $types = (array) ($raw['jobType'] ?? []);

        $currency = strtoupper(trim((string) ($raw['salaryCurrency'] ?? '')));
        $min = self::cents($raw['salaryMin'] ?? null);
        $max = self::cents($raw['salaryMax'] ?? null);

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $raw['id'],
            title: $title,
            companyName: self::text($raw['companyName'] ?? null, 190),
            industrySlug: self::industryFor($industries),
            workArrangement: 'remote_international',
            employmentType: self::employmentType($types[0] ?? null),
            locationText: $geoText !== '' ? "Remote — {$geoText}" : 'Remote',
            country: $geo['country'],
            isOpenToCaribbean: $geo['open'],
            geoEligibility: $geo['geo'],
            seniority: self::seniority([(string) ($raw['jobLevel'] ?? '')], $title),
            salaryMinCents: $min,
            salaryMaxCents: $max,
            salaryCurrency: ($min || $max) && $currency !== '' ? $currency : null,
            salaryPeriod: ($min || $max) ? self::period($raw['salaryPeriod'] ?? 'yearly') : null,
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::iso($raw['pubDate'] ?? null),
            applyUrl: $raw['url'] ?? null,
            rawPayload: [
                'jobIndustry' => $industries,
                'jobType' => $types,
                'jobGeo' => $geoText,
                'jobLevel' => $raw['jobLevel'] ?? null,
            ],
        );
    }
}
