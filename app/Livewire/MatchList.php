<?php

namespace App\Livewire;

use App\Jobs\RecomputeUserMatchesJob;
use App\Models\Application;
use App\Models\JobMatch;
use App\Services\SalaryFormatter;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Ranked matches (spec step 5). Every row shows the score, a plain-English
 * explanation, and what the user is missing. When nothing is eligible or
 * the best score is under the advisory threshold, the page pivots to the
 * skills-gap callout (full plan arrives in Phase 5) — never an empty state.
 */
class MatchList extends Component
{
    use WithPagination;

    #[Url]
    public bool $showIneligible = false;

    public function mount(): void
    {
        $user = Auth::user();

        // First visit: compute now so the page opens with scores rather than a poll.
        if ($user->profile()->exists() && ! $user->jobMatches()->exists()) {
            $this->runRecompute((int) $user->id);
        }
    }

    public function recompute(): void
    {
        session()->flash(
            'matches-recompute',
            $this->runRecompute((int) Auth::id())
                ? 'Your matches have been recomputed.'
                : 'Recomputing your matches — this takes a minute.',
        );
    }

    /**
     * Scores every open listing for the user. Inline by default: a few hundred
     * listings take a couple of seconds, and on shared hosting the queue is only
     * drained once a minute by cron. Returns true when the scores are already
     * fresh, false when the work was queued or failed and the poll should wait.
     */
    private function runRecompute(int $userId): bool
    {
        if (! config('matching.recompute_inline')) {
            RecomputeUserMatchesJob::dispatch($userId);

            return false;
        }

        try {
            app()->call([new RecomputeUserMatchesJob($userId), 'handle']);

            return true;
        } catch (Throwable $e) {
            report($e);
            RecomputeUserMatchesJob::dispatch($userId);

            return false;
        }
    }

    public function render(SettingsService $settings, SalaryFormatter $salary)
    {
        $user = Auth::user();
        $base = JobMatch::query()->where('user_id', $user->id);

        $matches = (clone $base)
            ->where('is_eligible', true)
            ->with('jobListing.industry')
            ->orderByDesc('score')
            ->orderByDesc('job_listing_id')
            ->paginate(20);

        $ineligibleCount = (clone $base)->where('is_eligible', false)->count();
        $ineligible = $this->showIneligible
            ? (clone $base)->where('is_eligible', false)->with('jobListing')->orderBy('ineligibility_reason')->limit(100)->get()
            : collect();

        $best = (int) ((clone $base)->where('is_eligible', true)->max('score') ?? 0);
        $threshold = $settings->advisoryThreshold();
        $lastComputed = (clone $base)->max('computed_at');

        $advisory = $matches->total() === 0 || $best < $threshold;

        return view('livewire.match-list', [
            'matches' => $matches,
            'ineligible' => $ineligible,
            'ineligibleCount' => $ineligibleCount,
            'best' => $best,
            'threshold' => $threshold,
            'advisory' => $advisory,
            'topGaps' => $advisory ? $this->topMissingSkills($user->id) : collect(),
            'lastComputed' => $lastComputed ? Carbon::parse($lastComputed) : null,
            'hasProfile' => $user->profile()->exists(),
            'hasPreferences' => $user->jobPreference()->exists(),
            'trackedStatuses' => Application::query()->where('user_id', $user->id)->pluck('status', 'job_listing_id'),
            'salary' => $salary,
        ]);
    }

    /**
     * Most frequently missing REQUIRED skills across the user's eligible
     * matches — the lightweight precursor to the Phase 5 SkillGapAnalyzer.
     */
    private function topMissingSkills(int $userId): Collection
    {
        return JobMatch::query()
            ->where('user_id', $userId)
            ->where('is_eligible', true)
            ->get(['missing_skills'])
            ->flatMap(fn (JobMatch $match) => collect($match->missing_skills ?? [])->where('required', true))
            ->groupBy('id')
            ->map(fn (Collection $group) => ['name' => $group->first()['name'], 'jobs' => $group->count()])
            ->sortByDesc('jobs')
            ->take(5)
            ->values();
    }
}
