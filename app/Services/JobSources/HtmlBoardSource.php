<?php

namespace App\Services\JobSources;

use App\Services\JobSources\Support\RobotsTxt;
use App\Services\Skills\SkillMatcher;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Base for the local-board crawlers (sites without an API). On top of
 * RemoteBoardSource it adds:
 *  - a robots.txt check for every URL before it is fetched (skipped pages
 *    are counted and reported on the sync dashboard);
 *  - a polite delay between page fetches and a page cap per run;
 *  - HTML parsing via Symfony DomCrawler.
 *
 * Each adapter's docblock records the site's robots.txt and terms as read
 * on the verification date, and whether it is enabled by default.
 */
abstract class HtmlBoardSource extends RemoteBoardSource
{
    protected int $robotsBlocked = 0;

    public function __construct(SkillMatcher $skills, protected readonly RobotsTxt $robots)
    {
        parent::__construct($skills);
    }

    /** Fetch a page as a crawler, or null when robots.txt disallows it. */
    protected function getHtml(string $url): ?Crawler
    {
        if (! $this->robots->allows($url)) {
            $this->robotsBlocked++;

            return null;
        }

        $delay = (int) config("jobsources.remote.boards.{$this->key()}.delay_ms", 1500);
        if ($delay > 0) {
            usleep($delay * 1000);
        }

        $response = $this->http()
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("{$this->key()}: HTTP {$response->status()} from {$url}");
        }

        return new Crawler((string) $response->body(), $url);
    }

    public function fetch(): iterable
    {
        yield from parent::fetch();

        if ($this->robotsBlocked > 0) {
            $this->note = trim(($this->note ?? '')." {$this->robotsBlocked} page(s) skipped: disallowed by robots.txt.");
        }
    }

    protected function maxPages(): int
    {
        return (int) config("jobsources.remote.boards.{$this->key()}.max_pages", 3);
    }

    /**
     * Industry slug inferred from a listing's title (and, failing that, its
     * excerpt). Local boards publish no category, and an unclassified listing
     * disappears from every industry-filtered view — see
     * config/jobsources.php 'title_industry_patterns'.
     */
    public static function industryFromTitle(string $title, ?string $excerpt = null): ?string
    {
        $patterns = config('jobsources.title_industry_patterns', []);

        foreach ([$title, trim($title.' '.(string) $excerpt)] as $haystack) {
            $haystack = mb_strtolower($haystack);
            if ($haystack === '') {
                continue;
            }
            foreach ($patterns as $slug => $pattern) {
                if (preg_match($pattern, $haystack)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /** Employment type when the title says so ("Temporary Administrative Assistant", "(3 month Contract)"). */
    public static function employmentFromTitle(string $title): ?string
    {
        $t = mb_strtolower($title);

        return match (true) {
            (bool) preg_match('/\b(temporary|temp|seasonal|part[- ]time|intern\w*|summer)\b/', $t) => 'temporary',
            (bool) preg_match('/\b(contract|fixed[- ]term|freelance)\b/', $t) => 'contract',
            (bool) preg_match('/\b(permanent|full[- ]time)\b/', $t) => 'permanent',
            default => null,
        };
    }

    /**
     * A listing's detail page, or null when it cannot be fetched. Detail pages
     * vanish between the index crawl and the follow-up request (filled or
     * withdrawn postings); losing one description must not abort the board.
     */
    protected function detailHtml(string $url): ?Crawler
    {
        try {
            return $this->getHtml($url);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Text of the first node matching a selector, or null. */
    protected static function nodeText(Crawler $node, string $selector): ?string
    {
        $found = $node->filter($selector);
        if ($found->count() === 0) {
            return null;
        }
        $text = html_entity_decode($found->first()->text(''), ENT_QUOTES | ENT_HTML5);

        // Some boards (CaribbeanJobs) interleave zero-width spaces between the
        // letters of titles as an anti-scraping measure. Left in, they break
        // search, skill detection and de-duplication while looking normal on screen.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}\x{00AD}]/u', '', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? null : $text;
    }

    /** Absolute href of the first matching link, or null. */
    protected static function nodeHref(Crawler $node, string $selector): ?string
    {
        $found = $node->filter($selector);
        if ($found->count() === 0) {
            return null;
        }

        try {
            return $found->first()->link()->getUri();
        } catch (\Throwable) {
            return $found->first()->attr('href');
        }
    }

    /** Parse a date in one of the given formats → UTC datetime string, or null. */
    protected static function parseDate(?string $text, array $formats): ?string
    {
        if ($text === null) {
            return null;
        }
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($text), config('app.display_timezone'));
                if ($date !== false) {
                    // Boards give dates, not times: pin to the start of that day (AST).
                    return $date->startOfDay()->utc()->toDateTimeString();
                }
            } catch (\Throwable) {
                // try next format
            }
        }

        return null;
    }
}
