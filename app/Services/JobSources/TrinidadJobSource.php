<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;

/**
 * TrinidadJob.com — local T&T board (WordPress + WP Job Manager).
 *
 *  Base URL     https://trinidadjob.com/wp-json/wp/v2/job-listings
 *               (?per_page=1..100, ?page=N, &_embed=1 for taxonomy terms)
 *  Auth         none — the standard public WordPress REST API
 *  Rate limit   none published; we read at most twice a day
 *               (config min_interval_minutes = 720) and one page per request
 *  robots.txt   "User-agent: * Disallow:" (allows all), read 2026-09-07
 *  Terms        no terms-of-use page exists (only a privacy policy, which has
 *               no clause on automated access, copying or aggregation);
 *               read 2026-09-07. Re-verify before relying on this.
 *  Attribution  "TrinidadJob.com" named on every listing; the apply link is
 *               the listing's canonical page on trinidadjob.com.
 *
 *  Fields used  id, date_gmt, link, title.rendered, content.rendered,
 *               meta._job_location/_company_name/_remote_position/_filled/
 *               _apply_link/_salary_min/_salary_max/_job_salary_currency/
 *               _job_salary_unit, _embedded["wp:term"] (job_listing_category,
 *               job_listing_type, job_listing_region)
 */
class TrinidadJobSource extends RemoteBoardSource
{
    public function key(): string
    {
        return 'trinidadjob';
    }

    protected function pages(): iterable
    {
        $url = config('jobsources.remote.boards.trinidadjob.url');
        $perPage = (int) config('jobsources.remote.boards.trinidadjob.per_page', 100);
        $maxPages = (int) config('jobsources.remote.boards.trinidadjob.max_pages', 2);

        for ($page = 1; $page <= $maxPages; $page++) {
            $posts = $this->getJson($url, ['per_page' => $perPage, 'page' => $page, '_embed' => 1]);

            // WordPress answers a page past the end with a 400 "rest_post_invalid_page_number";
            // getJson() would throw, so stop as soon as a page comes back short.
            yield $posts;

            if (count($posts) < $perPage) {
                break;
            }
        }
    }

    protected function map(array $raw): ?JobDto
    {
        $title = self::text($raw['title']['rendered'] ?? null, 255);
        if ($title === null || empty($raw['id']) || empty($raw['link'])) {
            return null;
        }

        $meta = (array) ($raw['meta'] ?? []);
        if ((int) ($meta['_filled'] ?? 0) === 1) {
            return null; // employer marked the position as filled
        }

        $terms = self::terms($raw);
        $categories = $terms['job_listing_category'];
        $types = $terms['job_listing_type'];
        $region = $terms['job_listing_region'][0] ?? null;

        $description = self::text($raw['content']['rendered'] ?? null);
        $skills = $this->skillsFrom($title, $categories, $description);

        $location = trim((string) ($meta['_job_location'] ?? '')) ?: $region;
        $locationText = $location !== null && $location !== ''
            ? (preg_match('/trinidad|tobago/i', $location) ? $location : "{$location}, Trinidad & Tobago")
            : 'Trinidad & Tobago';
        $isRemote = (int) ($meta['_remote_position'] ?? 0) === 1 || preg_match('/\bremote\b/i', $title);

        // The board's own categories are the best industry signal ("Construction /
        // Facilities/ Quantity Surveying"); the generic keyword map and finally the
        // title/description fill in when they say nothing.
        $categoryText = implode(' ', $categories);
        $industry = ($categoryText !== '' ? HtmlBoardSource::industryFromTitle($categoryText) : null)
            ?? self::industryFor($categories)
            ?? HtmlBoardSource::industryFromTitle($title, $description);

        $min = self::cents($meta['_salary_min'] ?? null);
        $max = self::cents($meta['_salary_max'] ?? null);
        $currency = strtoupper(trim((string) ($meta['_job_salary_currency'] ?? '')));

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $raw['id'],
            title: $title,
            companyName: self::text($meta['_company_name'] ?? null, 190),
            industrySlug: $industry,
            workArrangement: $isRemote ? 'hybrid_local' : 'on_premises',
            employmentType: self::employmentType($types[0] ?? null) ?? HtmlBoardSource::employmentFromTitle($title),
            locationText: $locationText,
            country: 'TT',
            isOpenToCaribbean: true,
            geoEligibility: 'worldwide',
            seniority: self::seniority($categories, $title),
            salaryMinCents: $min,
            salaryMaxCents: $max,
            salaryCurrency: ($min || $max) && preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null,
            salaryPeriod: ($min || $max) ? self::period($meta['_job_salary_unit'] ?? 'monthly') : null,
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::iso($raw['date_gmt'] ?? null),
            applyUrl: $raw['link'],
            rawPayload: [
                'categories' => $categories,
                'types' => $types,
                'region' => $region,
                'apply_link' => $meta['_apply_link'] ?? null,
            ],
        );
    }

    /** @return array{job_listing_category: list<string>, job_listing_type: list<string>, job_listing_region: list<string>} */
    private static function terms(array $raw): array
    {
        $out = ['job_listing_category' => [], 'job_listing_type' => [], 'job_listing_region' => []];

        foreach ((array) ($raw['_embedded']['wp:term'] ?? []) as $group) {
            foreach ((array) $group as $term) {
                $taxonomy = $term['taxonomy'] ?? null;
                $name = self::text($term['name'] ?? null, 190);
                if ($taxonomy !== null && $name !== null && array_key_exists($taxonomy, $out)) {
                    $out[$taxonomy][] = $name;
                }
            }
        }

        return $out;
    }
}
