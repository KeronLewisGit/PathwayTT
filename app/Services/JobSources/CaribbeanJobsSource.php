<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * CaribbeanJobs.com — Trinidad & Tobago results index (Location=124).
 *
 *  Pages        https://www.caribbeanjobs.com/ShowResults.aspx?Location=124&Page=N (25 per page)
 *  robots.txt   (read 2026-09-03) "User-agent: *  Allow: /" with
 *               "Content-Signal: search=yes, ai-train=no, use=reference" —
 *               general crawlers may build a search index of links and short
 *               excerpts; a few named AI crawlers are disallowed; four
 *               internal endpoints are disallowed (we don't touch them).
 *  Terms        /about/terms (read 2026-09-03): no clause on automated access
 *               or aggregation.
 *  Our use      matches the "search" signal: we index title, employer,
 *               location, date and the listing's own short excerpt, and link
 *               every result to the original posting. We do NOT fetch or
 *               store full descriptions and never train models on the data.
 *  Cadence      once per 24h (min_interval_minutes = 1440), 1.5s between pages.
 *  Default      enabled.
 */
class CaribbeanJobsSource extends HtmlBoardSource
{
    public function key(): string
    {
        return 'caribbeanjobs';
    }

    protected function pages(): iterable
    {
        $base = config('jobsources.remote.boards.caribbeanjobs.url');

        for ($page = 1; $page <= $this->maxPages(); $page++) {
            $crawler = $this->getHtml($base.($page > 1 ? "&Page={$page}" : ''));
            if ($crawler === null) {
                break;
            }

            $cards = $crawler->filter('.module.job-result')->each(fn (Crawler $card) => [
                'id' => $card->filter('h2[itemprop="title"] a')->count() ? $card->filter('h2[itemprop="title"] a')->attr('jobId') : null,
                'title' => self::nodeText($card, 'h2[itemprop="title"] a'),
                'url' => self::nodeHref($card, 'h2[itemprop="title"] a'),
                'company' => self::nodeText($card, 'h3[itemprop="name"]'),
                'locations' => $card->filter('li.location a')->each(fn (Crawler $a) => trim($a->text(''))),
                'updated' => self::nodeText($card, 'li.updated-time'),
                'salary' => self::nodeText($card, 'li.salary'),
                'excerpt' => self::nodeText($card, 'p[itemprop="description"]'),
            ]);

            yield $cards;

            if ($cards === [] || $crawler->filter('a[href*="Page='.($page + 1).'"]')->count() === 0) {
                break;
            }
        }
    }

    protected function map(array $raw): ?JobDto
    {
        if (empty($raw['title']) || empty($raw['url'])) {
            return null;
        }

        $id = $raw['id'] ?: (preg_match('/-(\d+)\.aspx/i', $raw['url'], $m) ? $m[1] : null);
        if ($id === null) {
            return null;
        }

        $locations = array_values(array_filter($raw['locations'] ?? []));
        $locationText = $locations !== [] ? implode(' / ', $locations).', Trinidad & Tobago' : 'Trinidad & Tobago';
        $isRemote = (bool) preg_match('/\bremote\b/i', $raw['title'].' '.$locationText);
        $excerpt = $raw['excerpt'] ?? null;
        $skills = $this->skillsFrom($raw['title'], [], $excerpt);

        return new JobDto(
            source: $this->key(),
            sourceJobId: (string) $id,
            title: $raw['title'],
            companyName: $raw['company'] ?: null,
            industrySlug: self::industryFromTitle($raw['title'], $excerpt),
            workArrangement: $isRemote ? 'hybrid_local' : 'on_premises',
            employmentType: self::employmentFromTitle($raw['title']),
            locationText: $locationText,
            country: 'TT',
            geoEligibility: 'worldwide',
            isOpenToCaribbean: true,
            seniority: self::seniority([], $raw['title']),
            description: $excerpt ? $excerpt."\n\nFull details on CaribbeanJobs.com (excerpt shown here)." : null,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::parseDate(preg_replace('/^Updated\s+/i', '', (string) $raw['updated']), ['d/m/Y']),
            applyUrl: $raw['url'],
            rawPayload: ['salary' => $raw['salary'], 'locations' => $locations],
        );
    }
}
