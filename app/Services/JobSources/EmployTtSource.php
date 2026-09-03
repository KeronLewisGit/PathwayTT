<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use Symfony\Component\DomCrawler\Crawler;

/**
 * EmployTT (employtt.gov.tt) — the Government of Trinidad & Tobago's
 * employment portal, operated by iGovTT.
 *
 *  Pages        https://employtt.gov.tt/jobs/list (single page; client-side
 *               pagination) and /jobs/view/{id} for descriptions.
 *  robots.txt   (read 2026-09-03) "User-agent: *  Disallow:" — crawling allowed.
 *  Terms        /terms (read 2026-09-03): "You may not … reproduce or publicly
 *               display … or otherwise use them or any part of them for any
 *               public or commercial purpose without the written permission of
 *               iGovTT" and "any use of these materials … on any other website
 *               … for any purpose is prohibited."
 *  Decision     DISABLED by default. Republishing listings needs written
 *               permission from iGovTT / the Ministry of Labour; a public-
 *               benefit job-matching service is a reasonable request. Set
 *               JOBSOURCE_EMPLOYTT=true once permission is on file.
 *  Cadence      once per 24h, 1.5s between requests; detail pages fetched
 *               only for new listings.
 */
class EmployTtSource extends HtmlBoardSource
{
    public function key(): string
    {
        return 'employtt';
    }

    protected function pages(): iterable
    {
        $crawler = $this->getHtml(config('jobsources.remote.boards.employtt.url'));
        if ($crawler === null) {
            return;
        }

        yield $crawler->filter('.single-job')->each(fn (Crawler $card) => [
            'title' => self::nodeText($card, '.job-title a'),
            'url' => self::nodeHref($card, '.job-title a'),
            'employer' => self::nodeText($card, '.employer-name'),
            'location' => self::nodeText($card, '.locationfilter'),
            'status' => self::nodeText($card, 'a.employmentStatus') ?? self::nodeText($card, '.employmentStatus'),
            'published' => self::nodeText($card, '.publishDateSort'),
            'deadline' => self::nodeText($card, '.deadlineDateSort'),
            'tags' => $card->filter('.job-skill-tag')->each(fn (Crawler $t) => trim($t->text(''))),
        ]);
    }

    protected function map(array $raw): ?JobDto
    {
        if (empty($raw['title']) || empty($raw['url']) || ! preg_match('~/jobs/view/(\d+)~', $raw['url'], $m)) {
            return null;
        }

        $description = null;
        if ((bool) config('jobsources.remote.boards.employtt.fetch_details', true)) {
            $detail = $this->getHtml($raw['url']);
            if ($detail !== null) {
                $description = self::text(implode("\n\n", $detail->filter('.field-descriptions')->each(fn (Crawler $d) => $d->html())));
            }
        }

        $skills = $this->skillsFrom($raw['title'], $raw['tags'] ?? [], $description);
        $status = strtolower((string) ($raw['status'] ?? ''));

        return new JobDto(
            source: $this->key(),
            sourceJobId: $m[1],
            title: html_entity_decode($raw['title'], ENT_QUOTES | ENT_HTML5),
            companyName: $raw['employer'] ?: null,
            industrySlug: self::industryFor($raw['tags'] ?? []),
            workArrangement: 'on_premises',
            employmentType: self::employmentType($status),
            locationText: ($raw['location'] ? $raw['location'].', ' : '').'Trinidad & Tobago',
            country: 'TT',
            geoEligibility: 'worldwide',
            isOpenToCaribbean: true,
            seniority: self::seniority([], $raw['title']),
            description: $description,
            requiredSkills: $skills['required'],
            preferredSkills: $skills['preferred'],
            postedAt: self::parseDate($raw['published'] ?? null, ['m/d/Y']),
            closesAt: self::parseDate($raw['deadline'] ?? null, ['m/d/Y']),
            applyUrl: $raw['url'],
            rawPayload: ['tags' => $raw['tags'] ?? [], 'status' => $status],
        );
    }
}
