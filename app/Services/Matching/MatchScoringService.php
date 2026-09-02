<?php

namespace App\Services\Matching;

use App\Enums\GeoEligibility;
use App\Enums\WorkArrangement;
use App\Models\JobListing;
use App\Models\Skill;
use App\Services\SettingsService;
use Illuminate\Support\Collection;

/**
 * 0–100 match score with a stored, explainable breakdown.
 *
 * Rules (docs/SPEC.md "Matching and scoring"):
 *  - hard filters run first and produce ineligibility, not a low score;
 *  - skills compare canonical taxonomy ids (aliases were resolved on both
 *    sides at ingest/parse time), never raw strings;
 *  - weights come from config/matching.php with admin overrides via the
 *    settings table; components a listing doesn't state (no preferred
 *    skills, no education requirement…) are "not applicable" and their
 *    weight is redistributed across the rest, so a sparse listing isn't
 *    penalised or inflated;
 *  - missing required skills cap the total (1 missing → 80, 2+ → 60).
 *
 * Breakdown shape (JSON on job_matches.score_breakdown):
 *  components: [{key,label,weight,score,points,applicable,detail}, …]
 *  raw_score, cap, cap_reason, gaps: [string, …], summary: string
 */
class MatchScoringService implements MatchScorerInterface
{
    private const LABELS = [
        'required_skills' => 'Required skills',
        'bonus_skills' => 'Nice-to-have skills',
        'experience' => 'Experience',
        'education' => 'Education',
        'industry' => 'Industry alignment',
        'arrangement' => 'Work arrangement',
        'geo_timezone' => 'Location & timezone',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function score(CandidateProfile $candidate, JobListing $job): MatchResult
    {
        // ── Hard filters ────────────────────────────────────────────
        if (! $job->isOpen()) {
            return MatchResult::ineligible('This listing is closed');
        }

        if ($reason = $job->ineligibilityReason()) {
            return MatchResult::ineligible($reason);
        }

        if ($job->requires_work_permit && $job->country !== null && $job->country !== 'TT') {
            return MatchResult::ineligible("Requires the right to work in {$job->country} (work permit or residency)");
        }

        $weights = $this->settings->matchingWeights();
        $components = [];
        $gaps = [];

        // ── Skills ──────────────────────────────────────────────────
        $skills = $job->relationLoaded('skills') ? $job->skills : $job->skills()->get();
        $required = $skills->filter(fn (Skill $s) => (bool) $s->pivot->is_required)->values();
        $preferred = $skills->reject(fn (Skill $s) => (bool) $s->pivot->is_required)->values();
        $missingRequired = $required->reject(fn (Skill $s) => $candidate->hasSkill($s->id))->values();
        $missingPreferred = $preferred->reject(fn (Skill $s) => $candidate->hasSkill($s->id))->values();

        $components[] = $this->component('required_skills', $weights,
            $required->isEmpty() ? null : self::coverage($required->count(), $missingRequired->count()),
            $required->isEmpty()
                ? 'No required skills listed'
                : sprintf('You have %d of %d required skills', $required->count() - $missingRequired->count(), $required->count())
                    .($missingRequired->isNotEmpty() ? ' (missing: '.$missingRequired->pluck('name')->join(', ').')' : ''),
        );

        $components[] = $this->component('bonus_skills', $weights,
            $preferred->isEmpty() ? null : self::coverage($preferred->count(), $missingPreferred->count()),
            $preferred->isEmpty()
                ? 'No nice-to-have skills listed'
                : sprintf('You have %d of %d nice-to-have skills', $preferred->count() - $missingPreferred->count(), $preferred->count()),
        );

        // ── Experience ──────────────────────────────────────────────
        $requiredYears = $job->requiredYears();
        if ($requiredYears === null || $requiredYears === 0) {
            $components[] = $this->component('experience', $weights, null,
                $requiredYears === 0 ? 'Entry level — no minimum experience' : 'No experience requirement stated');
        } elseif ($candidate->yearsExperience === null) {
            $components[] = $this->component('experience', $weights, 0, "Role expects about {$requiredYears} years; add your years of experience to your profile");
            $gaps[] = 'Add your years of experience to your profile';
        } else {
            $score = $candidate->yearsExperience >= $requiredYears
                ? 100
                : (int) round($candidate->yearsExperience / $requiredYears * 100);
            $components[] = $this->component('experience', $weights, $score,
                $score === 100
                    ? "Meets the ~{$requiredYears} years expected (you list {$candidate->yearsExperience})"
                    : "Role expects about {$requiredYears} years; you list {$candidate->yearsExperience}");
            if ($score < 100) {
                $gaps[] = "About {$requiredYears} years of experience expected (you list {$candidate->yearsExperience})";
            }
        }

        // ── Education ───────────────────────────────────────────────
        $requiredEducation = $job->min_education_level;
        if ($requiredEducation === null) {
            $components[] = $this->component('education', $weights, null, 'No qualification requirement stated');
        } elseif ($candidate->education === null) {
            $components[] = $this->component('education', $weights, 0, "Asks for {$requiredEducation->label()}; add your highest qualification to your profile");
            $gaps[] = 'Add your highest qualification to your profile';
        } else {
            $score = $candidate->education->rank() >= $requiredEducation->rank()
                ? 100
                : (int) round($candidate->education->rank() / $requiredEducation->rank() * 100);
            $components[] = $this->component('education', $weights, $score,
                $score === 100
                    ? "Your {$candidate->education->label()} meets the {$requiredEducation->label()} requirement"
                    : "Asks for {$requiredEducation->label()}; your highest is {$candidate->education->label()}");
            if ($score < 100) {
                $gaps[] = "{$requiredEducation->label()} expected (your highest is {$candidate->education->label()})";
            }
        }

        // ── Industry ────────────────────────────────────────────────
        if ($candidate->preferredIndustryId === null || $job->industry_id === null) {
            $components[] = $this->component('industry', $weights, null,
                $candidate->preferredIndustryId === null ? 'No target industry set in your preferences' : 'Listing has no industry');
        } else {
            $match = $candidate->preferredIndustryId === $job->industry_id;
            $components[] = $this->component('industry', $weights, $match ? 100 : 0,
                $match ? 'In your target industry' : 'Outside your target industry');
        }

        // ── Work arrangement ────────────────────────────────────────
        if ($candidate->workArrangements === []) {
            $components[] = $this->component('arrangement', $weights, null, 'No arrangement preference set');
        } else {
            $match = in_array($job->work_arrangement->value, $candidate->workArrangements, true);
            $components[] = $this->component('arrangement', $weights, $match ? 100 : 0,
                $match
                    ? $job->work_arrangement->label().' matches your preference'
                    : $job->work_arrangement->label().' is not in your preferred arrangements');
        }

        // ── Geo eligibility + timezone overlap ──────────────────────
        if ($job->work_arrangement !== WorkArrangement::RemoteInternational) {
            $components[] = $this->component('geo_timezone', $weights, null, 'Local role — no timezone constraint');
        } else {
            $geoScore = match (true) {
                $job->geo_eligibility === GeoEligibility::Worldwide => 100,
                $job->geo_eligibility === GeoEligibility::RegionRestricted && $job->is_open_to_caribbean === true => 100,
                $job->is_open_to_caribbean === true => 100,
                default => (int) config('matching.unclear_geo_score', 70),
            };
            $overlapScore = $this->overlapScore($job->required_overlap_hours);
            $score = (int) round($geoScore * $overlapScore / 100);

            $detail = $geoScore === 100 ? 'Open to applicants from T&T' : 'Eligibility from T&T not stated — check the posting';
            $detail .= $job->required_overlap_hours
                ? sprintf('; asks for %dh overlap with the employer\'s day (AST is UTC-4)', $job->required_overlap_hours)
                : '; no overlap hours stated';

            $components[] = $this->component('geo_timezone', $weights, $score, $detail);
        }

        // ── Credentials (advice, not a hard filter — all obtainable locally) ──
        foreach ($job->required_credentials ?? [] as $credential) {
            if (! ($candidate->credentials[$credential] ?? false)) {
                $label = JobListing::CREDENTIALS[$credential] ?? $credential;
                $gaps[] = "Requires a {$label} — mark it on your profile if you already have one";
            }
        }

        // ── Total: weighted average over applicable components ──────
        $applicable = array_filter($components, fn (array $c) => $c['applicable']);
        $weightSum = array_sum(array_column($applicable, 'weight'));
        $raw = $weightSum > 0
            ? (int) round(array_sum(array_map(fn (array $c) => $c['weight'] * $c['score'], $applicable)) / $weightSum)
            : 0;

        foreach ($components as &$component) {
            $component['points'] = $component['applicable'] && $weightSum > 0
                ? round($component['weight'] * $component['score'] / $weightSum, 1)
                : 0;
        }
        unset($component);

        $cap = $this->capFor($missingRequired->count());
        $capReason = $cap !== null
            ? sprintf('Missing %d required %s caps the score at %d', $missingRequired->count(), $missingRequired->count() === 1 ? 'skill' : 'skills', $cap)
            : null;

        // Confidence caps: no skills evidence on either side means the rest of
        // the factors would inflate the score into a false "perfect match".
        $confidence = config('matching.confidence_caps', []);
        if ($skills->isEmpty() && ! empty($confidence['listing_without_skills']) && ($cap === null || $confidence['listing_without_skills'] < $cap)) {
            $cap = (int) $confidence['listing_without_skills'];
            $capReason = "This listing states no skills we recognise, so the score is provisional (capped at {$cap})";
        }
        if ($candidate->skillIds === [] && ! empty($confidence['candidate_without_skills']) && ($cap === null || $confidence['candidate_without_skills'] < $cap)) {
            $cap = (int) $confidence['candidate_without_skills'];
            $capReason = "Your profile has no skills yet, so scores are capped at {$cap}";
            $gaps[] = 'Add your skills to your profile — matching only counts skills that are on it';
        }

        $score = $cap !== null ? min($raw, $cap) : $raw;

        $missingSkills = $missingRequired->map(fn (Skill $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'required' => true])
            ->concat($missingPreferred->map(fn (Skill $s) => ['id' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'required' => false]))
            ->values()
            ->all();

        $summary = collect($applicable)->pluck('detail')->join('. ');
        if ($capReason) {
            $summary .= ". {$capReason}";
        }

        return new MatchResult(
            eligible: true,
            ineligibilityReason: null,
            score: max(0, min(100, $score)),
            breakdown: [
                'components' => array_values($components),
                'raw_score' => $raw,
                'cap' => $cap,
                'cap_reason' => $capReason,
                'gaps' => $gaps,
                'summary' => $summary.'.',
            ],
            missingSkills: $missingSkills,
        );
    }

    /** @return array{key:string,label:string,weight:int,score:int,applicable:bool,detail:string} */
    private function component(string $key, array $weights, ?int $score, string $detail): array
    {
        return [
            'key' => $key,
            'label' => self::LABELS[$key],
            'weight' => (int) ($weights[$key] ?? 0),
            'score' => $score ?? 0,
            'applicable' => $score !== null,
            'detail' => $detail,
        ];
    }

    private static function coverage(int $total, int $missing): int
    {
        return (int) round(($total - $missing) / $total * 100);
    }

    /** config: [max_required_overlap_hours => component score], ascending. */
    private function overlapScore(?int $requiredHours): int
    {
        if ($requiredHours === null || $requiredHours === 0) {
            return 100;
        }

        $bands = config('matching.overlap_feasibility', [6 => 100, 8 => 70, 24 => 40]);
        ksort($bands);

        foreach ($bands as $maxHours => $score) {
            if ($requiredHours <= $maxHours) {
                return (int) $score;
            }
        }

        return (int) end($bands);
    }

    /** config: [missing_required_count => cap]; the largest threshold ≤ count applies. */
    private function capFor(int $missingRequired): ?int
    {
        if ($missingRequired === 0) {
            return null;
        }

        $caps = config('matching.required_skill_caps', []);
        krsort($caps);

        foreach ($caps as $threshold => $cap) {
            if ($missingRequired >= (int) $threshold) {
                return (int) $cap;
            }
        }

        return null;
    }

    /** Human-readable labels for the breakdown keys (used by admin settings). */
    public static function labels(): array
    {
        return self::LABELS;
    }

    /** @param Collection<int, Skill> $skills */
    public static function skillNames(Collection $skills): string
    {
        return $skills->pluck('name')->join(', ');
    }
}
