<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Models\JobSyncRun;
use App\Services\Skills\SkillMatcher;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Shared plumbing for the public remote-job board adapters (Phase 6).
 *
 * Every adapter documents its board's base URL, auth, rate limit and
 * attribution terms in its class docblock — those were verified live on
 * 2026-09-02; re-verify if a board changes its terms.
 *
 * What the base gives each adapter:
 *  - a polite HTTP client (identifying User-Agent, timeout, retry);
 *  - per-board throttling so manual "Run sync now" clicks can't breach a
 *    board's request limits (min_interval_minutes in config/jobsources.php);
 *  - a cap on listings per run, and skipping of listings that a T&T resident
 *    can't apply to (import_ineligible=false) to keep the table lean;
 *  - skill extraction: title-matched skills and tags-that-also-appear-in-the-
 *    description are "required", everything else detected is "nice to have";
 *  - category → industry mapping via keywords.
 */
abstract class RemoteBoardSource implements JobSourceInterface
{
    protected ?string $note = null;

    public function __construct(protected readonly SkillMatcher $skills) {}

    abstract public function key(): string;

    /** Raw job arrays, one page at a time. */
    abstract protected function pages(): iterable;

    /** Normalise one raw job; return null to skip it. */
    abstract protected function map(array $raw): ?JobDto;

    public function enabled(): bool
    {
        return (bool) config("jobsources.remote.boards.{$this->key()}.enabled", false);
    }

    public function notes(): ?string
    {
        return $this->note;
    }

    public function fetch(): iterable
    {
        if ($this->throttled()) {
            $this->note = sprintf(
                'Skipped: fetched successfully less than %d minutes ago (board rate limit).',
                $this->minInterval(),
            );

            return;
        }

        $max = (int) config('jobsources.remote.max_per_source', 500);
        $importIneligible = (bool) config('jobsources.remote.import_ineligible', false);
        $yielded = 0;
        $skipped = 0;

        foreach ($this->pages() as $page) {
            foreach ($page as $raw) {
                if ($yielded >= $max) {
                    break 2;
                }

                $dto = is_array($raw) ? $this->map($raw) : null;
                if ($dto === null) {
                    continue;
                }

                if (! $importIneligible && ! self::openToTrinidad($dto)) {
                    $skipped++;

                    continue;
                }

                $yielded++;
                yield $dto;
            }
        }

        $this->note = $skipped > 0
            ? "{$skipped} listings skipped: not open to applicants from Trinidad & Tobago."
            : null;
    }

    // ── HTTP ──────────────────────────────────────────────────────────

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => config('jobsources.remote.user_agent'),
            'Accept' => 'application/json',
        ])
            ->timeout((int) config('jobsources.remote.timeout', 30))
            ->retry(2, 1500, throw: false);
    }

    protected function getJson(string $url, array $query = []): array
    {
        $response = $this->http()->get($url, $query);

        if ($response->failed()) {
            throw new RuntimeException("{$this->key()}: HTTP {$response->status()} from {$url}");
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException("{$this->key()}: non-JSON response from {$url}");
        }

        return $json;
    }

    private function throttled(): bool
    {
        $minutes = $this->minInterval();
        if ($minutes <= 0) {
            return false;
        }

        // Only a run that actually brought listings back starts the window;
        // an empty run (robots block, parser drift, outage) may retry sooner.
        return JobSyncRun::query()
            ->where('source', $this->key())
            ->whereNull('error')
            ->whereNotNull('finished_at')
            ->where('fetched_count', '>', 0)
            ->where('finished_at', '>', now()->subMinutes($minutes))
            ->exists();
    }

    private function minInterval(): int
    {
        return (int) config("jobsources.remote.boards.{$this->key()}.min_interval_minutes", 60);
    }

    // ── Normalisation helpers ─────────────────────────────────────────

    protected static function openToTrinidad(JobDto $dto): bool
    {
        if ($dto->geoEligibility === 'country_restricted' && $dto->country !== null && $dto->country !== 'TT') {
            return false;
        }

        if ($dto->geoEligibility === 'region_restricted' && $dto->isOpenToCaribbean === false) {
            return false;
        }

        return true;
    }

    /** HTML → readable plain text, bounded so descriptions can't bloat the table. */
    protected static function text(?string $html, int $limit = 20000): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>|<\/(p|div|li|h[1-6]|tr)>/i', "\n", $text);
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text === '' ? null : Str::limit($text, $limit, '…');
    }

    /** @return array{required: list<string>, preferred: list<string>} canonical skill names */
    protected function skillsFrom(string $title, array $tags, ?string $description): array
    {
        $inTitle = $this->skills->match($title);
        $inTags = $this->skills->matchTerms($tags);
        $inText = $this->skills->match(Str::limit((string) $description, 15000, ''));

        $required = $inTitle + array_intersect_key($inTags, $inText);
        $preferred = array_diff_key($inTags + $inText, $required);

        return ['required' => array_values($required), 'preferred' => array_values($preferred)];
    }

    /** Board category/industry strings → our industry slug via keyword table. */
    protected static function industryFor(array $categories): ?string
    {
        $haystack = mb_strtolower(implode(' ', array_map('strval', $categories)));
        if (trim($haystack) === '') {
            return null;
        }

        foreach (config('jobsources.remote.industry_keywords', []) as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    protected static function employmentType(?string $raw): ?string
    {
        $raw = mb_strtolower((string) $raw);

        return match (true) {
            $raw === '' => null,
            str_contains($raw, 'full') || str_contains($raw, 'permanent') => 'permanent',
            str_contains($raw, 'contract') || str_contains($raw, 'freelance') => 'contract',
            str_contains($raw, 'part') || str_contains($raw, 'temp') || str_contains($raw, 'intern') => 'temporary',
            default => null,
        };
    }

    /** @param list<string> $levels board-provided seniority labels */
    protected static function seniority(array $levels, string $title): ?string
    {
        $text = mb_strtolower(implode(' ', $levels).' '.$title);

        return match (true) {
            (bool) preg_match('/\b(director|head of|vp|vice president|chief|manager|management)\b/', $text) => 'manager',
            (bool) preg_match('/\b(senior|sr\.?|lead|principal|staff)\b/', $text) => 'senior',
            (bool) preg_match('/\b(junior|jr\.?|entry|graduate|intern|associate|trainee)\b/', $text) => 'entry',
            (bool) preg_match('/\b(mid|intermediate)\b/', $text) => 'mid',
            default => null,
        };
    }

    protected static function cents(int|float|string|null $amount): ?int
    {
        if ($amount === null || $amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
            return null;
        }

        return (int) round((float) $amount * 100);
    }

    protected static function period(?string $raw): ?string
    {
        $raw = mb_strtolower((string) $raw);

        return match (true) {
            $raw === '' => null,
            str_contains($raw, 'hour') => 'hourly',
            str_contains($raw, 'month') => 'monthly',
            str_contains($raw, 'year') || str_contains($raw, 'annual') => 'yearly',
            default => null,
        };
    }

    protected static function iso(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function fromUnix(int|string|null $timestamp): ?string
    {
        return $timestamp ? \Illuminate\Support\Carbon::createFromTimestampUTC((int) $timestamp)->toDateTimeString() : null;
    }
}
