<?php

namespace App\Services\Advisory;

use App\Enums\ProviderType;
use App\Enums\WorkArrangement;
use App\Models\JobListing;
use App\Models\JobPreference;
use App\Models\LearningResource;
use App\Models\Profile;
use App\Models\User;
use App\Services\Matching\CandidateProfile;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorerInterface;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The advisory-mode engine (docs/SPEC.md "Advisory / Skills Gap mode").
 *
 *  1. Scope: open, T&T-eligible listings in the user's target industry +
 *     arrangements (widened to everything if that leaves too few).
 *  2. Score them all for the user as they are today, aggregate the
 *     missing skills, weighted by frequency and how close the user
 *     already is to each listing.
 *  3. For each candidate gap, re-score every affected listing with that
 *     one skill added: the projected lift and the number of listings that
 *     cross the advisory threshold are REAL numbers from the DB, not guesses.
 *  4. Map each gap to catalogued learning resources, split into
 *     local-T&T and international/online tracks; estimate effort from the
 *     cheapest catalogued route (explicit duration, else credential type).
 *  5. Rank by impact per week of effort and bucket into Quick wins /
 *     Core credential / Long-term.
 *  6. Add non-credential advice only where the listings support it.
 *
 * Payload shape (persisted verbatim on skill_gap_plans.payload):
 *  version, threshold, scope{industry_id,industry,arrangements,listings_considered,widened},
 *  current{eligible_count,best_score,above_threshold},
 *  gaps[] (ranked), phases{quick,core,long}[] of gap items, advice[]
 */
class SkillGapAnalyzer implements SkillGapAnalyzerInterface
{
    public const VERSION = 1;

    public function __construct(
        private readonly MatchScorerInterface $scorer,
        private readonly SettingsService $settings,
    ) {}

    public function analyze(User $user): array
    {
        $candidate = CandidateProfile::fromUser($user);
        $profile = $user->profile()->first();
        $pref = $user->jobPreference()->first();
        $threshold = $this->settings->advisoryThreshold();

        [$listings, $scope] = $this->scopedListings($pref);

        // ── Current standing ────────────────────────────────────────
        /** @var array<int, MatchResult> $current */
        $current = [];
        foreach ($listings as $listing) {
            $result = $this->scorer->score($candidate, $listing);
            if ($result->eligible) {
                $current[$listing->id] = $result;
            }
        }

        $scores = array_map(fn (MatchResult $r) => $r->score, $current);
        $standing = [
            'eligible_count' => count($current),
            'best_score' => $scores === [] ? 0 : max($scores),
            'above_threshold' => count(array_filter($scores, fn (int $s) => $s >= $threshold)),
        ];

        // ── Aggregate gaps ──────────────────────────────────────────
        $gaps = [];
        foreach ($current as $listingId => $result) {
            foreach ($result->missingSkills as $skill) {
                $gap = &$gaps[$skill['id']];
                $gap ??= [
                    'skill' => ['id' => $skill['id'], 'name' => $skill['name'], 'slug' => $skill['slug']],
                    'required_in' => 0,
                    'preferred_in' => 0,
                    'listing_ids' => [],
                    'closeness' => 0.0,
                ];
                $skill['required'] ? $gap['required_in']++ : $gap['preferred_in']++;
                $gap['listing_ids'][] = $listingId;
                // Closer listings weigh more: a gap on a 70 matters more than on a 10.
                $gap['closeness'] += ($skill['required'] ? 1.0 : 0.5) * ($result->score / 100);
                unset($gap);
            }
        }

        $shortlist = collect($gaps)
            ->sortByDesc(fn (array $g) => ($g['required_in'] + 0.5 * $g['preferred_in']) * (1 + $g['closeness']))
            ->take((int) config('advisory.max_gaps'));

        // ── What-if projections + resources ─────────────────────────
        $resourcesBySkill = $this->resourcesBySkill($shortlist->keys()->all());
        $listingsById = $listings->keyBy('id');
        $items = [];

        foreach ($shortlist as $skillId => $gap) {
            $withSkill = $candidate->withSkill((int) $skillId);
            $totalLift = 0;
            $unlocked = 0;
            $bestAfter = 0;

            foreach ($gap['listing_ids'] as $listingId) {
                $before = $current[$listingId]->score;
                $after = $this->scorer->score($withSkill, $listingsById[$listingId])->score;
                $totalLift += max(0, $after - $before);
                $bestAfter = max($bestAfter, $after);
                if ($before < $threshold && $after >= $threshold) {
                    $unlocked++;
                }
            }

            $affected = count($gap['listing_ids']);
            $tracks = $resourcesBySkill[$skillId] ?? ['local' => [], 'online' => []];
            [$effortWeeks, $effortEstimated] = $this->effortFor($tracks);

            $impact = $totalLift + $unlocked * (int) config('advisory.unlock_bonus');

            $items[] = [
                'skill' => $gap['skill'],
                'jobs_requiring' => $gap['required_in'],
                'jobs_preferring' => $gap['preferred_in'],
                'jobs_affected' => $affected,
                'jobs_unlocked' => $unlocked,
                'avg_lift' => $affected > 0 ? (int) round($totalLift / $affected) : 0,
                'best_after' => $bestAfter,
                'effort_weeks' => $effortWeeks,
                'effort_estimated' => $effortEstimated,
                'impact' => $impact,
                'impact_per_week' => round($impact / max(1, $effortWeeks), 2),
                'phase' => $this->phaseFor($effortWeeks),
                'resources' => $tracks,
            ];
        }

        usort($items, fn (array $a, array $b) => [$b['impact_per_week'], $b['jobs_unlocked'], $b['impact']] <=> [$a['impact_per_week'], $a['jobs_unlocked'], $a['impact']]);
        foreach ($items as $i => &$item) {
            $item['rank'] = $i + 1;
        }
        unset($item);

        $phases = ['quick' => [], 'core' => [], 'long' => []];
        foreach ($items as $item) {
            $phases[$item['phase']][] = $item;
        }

        return [
            'version' => self::VERSION,
            'threshold' => $threshold,
            'scope' => $scope,
            'current' => $standing,
            'gaps' => $items,
            'phases' => [
                ['key' => 'quick', 'title' => 'Quick wins', 'window' => 'up to 4 weeks', 'items' => $phases['quick']],
                ['key' => 'core', 'title' => 'Core credential', 'window' => '1 to 6 months', 'items' => $phases['core']],
                ['key' => 'long', 'title' => 'Long-term', 'window' => '6 months and beyond', 'items' => $phases['long']],
            ],
            'advice' => $this->advice($listings, $current, $candidate, $profile, $pref),
        ];
    }

    /**
     * @return array{0: Collection<int, JobListing>, 1: array<string, mixed>}
     */
    private function scopedListings(?JobPreference $pref): array
    {
        $industryId = $pref?->industry_id;
        $arrangements = $pref?->work_arrangements ?? [];
        $cap = (int) config('advisory.listing_cap');

        $query = fn (bool $filtered) => JobListing::query()
            ->active()
            ->eligibleFromTT()
            ->with(['skills', 'industry'])
            ->when($filtered && $industryId, fn ($q) => $q->where('industry_id', $industryId))
            ->when($filtered && $arrangements !== [], fn ($q) => $q->whereIn('work_arrangement', $arrangements))
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->limit($cap)
            ->get();

        $filtered = $industryId !== null || $arrangements !== [];
        $listings = $query($filtered);
        $widened = false;

        if ($filtered && $listings->count() < (int) config('advisory.min_scope_listings')) {
            $listings = $query(false);
            $widened = true;
        }

        return [$listings, [
            'industry_id' => $industryId,
            'industry' => $industryId ? $pref->industry()->value('name') : null,
            'arrangements' => $arrangements,
            'listings_considered' => $listings->count(),
            'widened' => $widened,
        ]];
    }

    /**
     * Catalogued resources per skill, split into the two tracks the spec
     * requires. Hybrid providers sit in the local track (obtainable in T&T).
     *
     * @param list<int> $skillIds
     * @return array<int, array{local: list<array>, online: list<array>}>
     */
    private function resourcesBySkill(array $skillIds): array
    {
        if ($skillIds === []) {
            return [];
        }

        $perTrack = (int) config('advisory.resources_per_track');
        $out = [];

        $resources = LearningResource::query()
            ->whereHas('skills', fn ($q) => $q->whereIn('skills.id', $skillIds))
            ->with(['skills' => fn ($q) => $q->whereIn('skills.id', $skillIds)])
            ->get();

        foreach ($resources as $resource) {
            $snapshot = $this->snapshot($resource);
            $track = $resource->provider_type === ProviderType::InternationalOnline ? 'online' : 'local';

            foreach ($resource->skills as $skill) {
                $out[$skill->id] ??= ['local' => [], 'online' => []];
                $out[$skill->id][$track][] = $snapshot + ['impact_weight' => (int) $skill->pivot->impact_weight];
            }
        }

        foreach ($out as &$tracks) {
            foreach ($tracks as &$list) {
                usort($list, fn (array $a, array $b) => [$b['impact_weight'], $a['effort_weeks']] <=> [$a['impact_weight'], $b['effort_weeks']]);
                $list = array_slice($list, 0, $perTrack);
            }
            unset($list);
        }
        unset($tracks);

        return $out;
    }

    private function snapshot(LearningResource $resource): array
    {
        $estimated = $resource->duration_weeks === null;
        $effort = $resource->duration_weeks
            ?? config('advisory.effort_by_credential')[$resource->credential_type] ?? (int) config('advisory.unknown_effort_weeks');

        $cost = null;
        if ($resource->cost_min_cents !== null || $resource->cost_max_cents !== null) {
            $currency = strtoupper((string) $resource->currency);
            $cost = match (true) {
                $resource->cost_min_cents !== null && $resource->cost_max_cents !== null && $resource->cost_min_cents !== $resource->cost_max_cents
                    => "{$currency} ".Money::format($resource->cost_min_cents).'–'.Money::format($resource->cost_max_cents),
                $resource->cost_min_cents !== null => "{$currency} ".Money::format($resource->cost_min_cents),
                default => "{$currency} up to ".Money::format($resource->cost_max_cents),
            };
        }

        return [
            'id' => $resource->id,
            'title' => $resource->title,
            'provider' => $resource->provider,
            'provider_type' => $resource->provider_type->value,
            'delivery_mode' => $resource->delivery_mode,
            'url' => $resource->url,
            'credential_type' => $resource->credential_type,
            'duration_weeks' => $resource->duration_weeks,
            'effort_weeks' => (int) $effort,
            'effort_estimated' => $estimated,
            'cost' => $cost,
            'cost_note' => $resource->cost_note,
            'notes' => $resource->notes,
        ];
    }

    /** @return array{0:int,1:bool} cheapest effort across both tracks, and whether it is an estimate */
    private function effortFor(array $tracks): array
    {
        $all = array_merge($tracks['local'], $tracks['online']);

        if ($all === []) {
            return [(int) config('advisory.unknown_effort_weeks'), true];
        }

        usort($all, fn (array $a, array $b) => $a['effort_weeks'] <=> $b['effort_weeks']);

        return [$all[0]['effort_weeks'], (bool) $all[0]['effort_estimated']];
    }

    private function phaseFor(int $weeks): string
    {
        $windows = config('advisory.phase_windows');

        return match (true) {
            $weeks <= $windows['quick'] => 'quick',
            $weeks <= $windows['core'] => 'core',
            default => 'long',
        };
    }

    /**
     * Non-credential advice, each item backed by counts from the scoped
     * listings so nothing is suggested "just because".
     *
     * @param Collection<int, JobListing> $listings
     * @param array<int, MatchResult> $current
     */
    private function advice(Collection $listings, array $current, CandidateProfile $candidate, ?Profile $profile, ?JobPreference $pref): array
    {
        $advice = [];
        $eligible = $listings->filter(fn (JobListing $l) => isset($current[$l->id]));

        $remoteCount = $eligible->filter(fn (JobListing $l) => $l->work_arrangement === WorkArrangement::RemoteInternational)->count();
        if ($remoteCount > 0 && ! $this->hasRemoteSignals($profile)) {
            $advice[] = [
                'key' => 'remote_portfolio',
                'title' => 'Show your work online',
                'body' => "{$remoteCount} remote listings in your scope hire internationally. Foreign employers can't check local references easily — a portfolio site or GitHub profile with two or three finished pieces of work does that job for you.",
                'jobs' => $remoteCount,
            ];
            $advice[] = [
                'key' => 'usd_payments',
                'title' => 'Be ready to receive USD',
                'body' => 'Remote employers pay in USD, usually as a contractor. Open a Wise or Payoneer account (or confirm your bank accepts USD wires) before you apply, and say so in your application — it removes a common reason T&T candidates get passed over.',
                'jobs' => $remoteCount,
            ];
            $advice[] = [
                'key' => 'overlap_statement',
                'title' => 'State your timezone overlap on your CV',
                'body' => 'Trinidad & Tobago is AST (UTC-4) with no daylight saving, which matches US Eastern from March to November. Put a line like "Available 9am–5pm AST (US Eastern / Atlantic overlap)" at the top of your CV so screeners see it immediately.',
                'jobs' => $remoteCount,
            ];
        }

        foreach (JobListing::CREDENTIALS as $key => $label) {
            if ($candidate->credentials[$key] ?? false) {
                continue;
            }
            $count = $eligible->filter(fn (JobListing $l) => in_array($key, $l->required_credentials ?? [], true))->count();
            if ($count > 0) {
                $advice[] = [
                    'key' => "credential_{$key}",
                    'title' => "Get your {$label} in order",
                    'body' => "{$count} listings in your scope require a {$label}. If you already have one, tick it on your profile; if not, it's obtainable locally and worth sorting out before you apply.",
                    'jobs' => $count,
                ];
            }
        }

        if ($candidate->education === null) {
            $count = $eligible->filter(fn (JobListing $l) => $l->min_education_level !== null)->count();
            if ($count > 0) {
                $advice[] = [
                    'key' => 'add_education',
                    'title' => 'Add your highest qualification',
                    'body' => "{$count} listings state a minimum qualification and you're currently scoring zero on that factor because your profile has none recorded. CSEC/CAPE passes count.",
                    'jobs' => $count,
                ];
            }
        }

        if ($candidate->yearsExperience === null) {
            $count = $eligible->filter(fn (JobListing $l) => ($l->requiredYears() ?? 0) > 0)->count();
            if ($count > 0) {
                $advice[] = [
                    'key' => 'add_experience',
                    'title' => 'Add your years of experience',
                    'body' => "{$count} listings expect a minimum experience level; your profile doesn't say how many years you have, so that factor scores zero today.",
                    'jobs' => $count,
                ];
            }
        }

        if ($pref === null) {
            $advice[] = [
                'key' => 'set_preferences',
                'title' => 'Set your job preferences',
                'body' => 'Target industry and work arrangement are 20% of every score and they focus this plan on the listings you actually want.',
                'jobs' => $eligible->count(),
            ];
        }

        return $advice;
    }

    private function hasRemoteSignals(?Profile $profile): bool
    {
        if ($profile === null) {
            return false;
        }

        $pattern = config('advisory.remote_signal_pattern');
        $text = collect([$profile->summary])
            ->merge($profile->workHistories()->pluck('description'))
            ->merge($profile->workHistories()->pluck('title'))
            ->filter()
            ->join(' ');

        if ($text !== '' && preg_match($pattern, $text)) {
            return true;
        }

        return $profile->skills()->where('category', 'remote-work')->exists();
    }
}
