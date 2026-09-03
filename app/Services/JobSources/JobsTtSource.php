<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * JobsTT (jobstt.com) — commercial Trinidad & Tobago job board.
 *
 *  Pages        https://www.jobstt.com/job?page=N (10 per page) and
 *               https://www.jobstt.com/job/{slug} for descriptions.
 *  robots.txt   (read 2026-09-03) "User-agent: *  Disallow:" — crawling allowed.
 *  Terms        /page/terms-and-conditions (read 2026-09-03) prohibit users
 *               to "(d) use any data mining, robots or similar data gathering
 *               or extraction methods" and to "(h) aggregate, copy or
 *               duplicate in any manner any of the JobsTT Content".
 *  Decision     DISABLED by default: running this without JobsTT's consent
 *               would breach their terms. Ask JobsTT for a feed or written
 *               permission, then set JOBSOURCE_JOBSTT=true.
 *  Cadence      once per 24h, 1.5s between requests.
 */
class JobsTtSource extends HtmlBoardSource
{
    public function key(): string
    {
        return 'jobstt';
    }

    protected function pages(): iterable
    {
        $base = config('jobsources.remote.boards.jobstt.url');

        for ($page = 1; $page <= $this->maxPages(); $page++) {
            $crawler = $this->getHtml($base.($page > 1 ? "?page={$page}" : ''));
            if ($crawler === null) {
                break;
            }

            $cards = $crawler->filter('.job-block')->each(function (Crawler $card) {
                $info = ['category' => null, 'type' => null, 'location' => null, 'date' => null, 'salary' => null];
                $card->filter('ul.job-info li')->each(function (Crawler $li) use (&$info) {
                    $icon = $li->filter('span.icon, i.icon')->count() ? (string) $li->filter('span.icon, i.icon')->attr('class') : '';
                    $text = trim(preg_replace('/\s+/', ' ', $li->text('')));
                    match (true) {
                        str_contains($icon, 'briefcase') => $info['category'] = $text,
                        str_contains($icon, 'business-time') => $info['type'] = $text,
                        str_contains($icon, 'map-locator') => $info['location'] = $text,
                        str_contains($icon, 'clock') => $info['date'] = $text,
                        str_contains($icon, 'money') => $info['salary'] = $text,
                        default => null,
                    };
                });

                return [
                    'title' => self::nodeText($card, 'h4 a'),
                    'url' => self::nodeHref($card, 'h4 a'),
                    'company' => self::nodeText($card, 'ul.job-info li strong'),
                ] + $info;
            });

            yield $cards;

            if ($cards === [] || $crawler->filter('a[href*="page='.($page + 1).'"]')->count() === 0) {
                break;
            }
        }
    }

    protected function map(array $raw): ?JobDto
    {
        if (empty($raw['title']) || empty($raw['url']) || ! preg_match('~/job/([^/?#]+)~', $raw['url'], $m)) {
            return null;
        }

        $description = null;
        if ((bool) config('jobsources.remote.boards.jobstt.fetch_details', true)) {
            $detail = $this->getHtml($raw['url']);
            if ($detail !== null && $detail->filter('.job-detail-section .content')->count()) {
                $description = self::text($detail->filter('.job-detail-section .content')->first()->html());
            }
        }

        $skills = $this->skillsFrom($raw['title'], array_filter([$raw['category'] ?? null]), $description);
        $type = strtolower((string) ($raw['type'] ?? ''));
        $isRemote = (bool) preg_match('/\bremote\b/i', $raw['title'].' '.($raw['location'] ?? ''));

        return new JobDto(
            source: $this->key(),
            sourceJobId: $m[1],
            title: $raw['title'],
            companyName: $raw['company'] ?: null,
            industrySlug: self::industryFor(array_filter([$raw['category'] ?? null])),
            workArrangement: $isRemote ? 'hybrid_local' : 'on_premises',
            employmentType: str_contains($type, 'contract') ? 'contract' : self::employmentType($type),
            locationText: ($raw['location'] ? $raw['location'].', ' : '').'Trinidad & Tobago',
            country: 'TT',
            geoEligibility: 'worldwide',
            isOpenToCaribbean: true,
            seniority: self::seniority([], $raw['title']),
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::parseDate($raw['date'] ?? null, ['m/d/Y']),
            applyUrl: $raw['url'],
            rawPayload: ['category' => $raw['category'], 'type' => $raw['type'], 'salary' => $raw['salary']],
        );
    }
}
