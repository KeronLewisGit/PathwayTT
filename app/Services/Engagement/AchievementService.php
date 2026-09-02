<?php

namespace App\Services\Engagement;

use App\Enums\ApplicationStatus;
use App\Models\Achievement;
use App\Models\JobMatch;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Milestones tied to real job-search progress — never to time spent in
 * the app. Definitions are code (title, hint, points, check); the
 * achievements table records when each user reached one.
 */
class AchievementService
{
    public function __construct(
        private readonly ProfileStrength $strength,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, array{title:string,hint:string,icon:string,points:int}>
     */
    public static function definitions(): array
    {
        return [
            'resume_uploaded' => ['title' => 'Resume in', 'hint' => 'Upload your resume', 'icon' => '📄', 'points' => 10],
            'profile_half' => ['title' => 'Halfway there', 'hint' => 'Reach 50% profile strength', 'icon' => '🧭', 'points' => 15],
            'profile_full' => ['title' => 'Profile complete', 'hint' => 'Reach 100% profile strength', 'icon' => '✅', 'points' => 30],
            'skills_5' => ['title' => 'Skilled up', 'hint' => 'List 5 skills on your profile', 'icon' => '🛠️', 'points' => 15],
            'preferences_set' => ['title' => 'Aimed', 'hint' => 'Set your job preferences', 'icon' => '🎯', 'points' => 10],
            'first_match' => ['title' => 'On the board', 'hint' => 'Score at or above the match threshold on a listing', 'icon' => '📈', 'points' => 20],
            'plan_generated' => ['title' => 'Plan in hand', 'hint' => 'Generate your Skills Gap Plan', 'icon' => '🗺️', 'points' => 10],
            'gap_closed' => ['title' => 'Gap closed', 'hint' => 'Add a skill your plan recommended', 'icon' => '🔓', 'points' => 25],
            'first_saved' => ['title' => 'Shortlisted', 'hint' => 'Save a job to your tracker', 'icon' => '⭐', 'points' => 5],
            'first_applied' => ['title' => 'In the running', 'hint' => 'Mark a job as applied', 'icon' => '🚀', 'points' => 20],
            'applied_5' => ['title' => 'Persistent', 'hint' => 'Apply to 5 jobs', 'icon' => '💪', 'points' => 30],
            'first_interview' => ['title' => 'Interview stage', 'hint' => 'Reach an interview', 'icon' => '🤝', 'points' => 40],
            'first_offer' => ['title' => 'Offer!', 'hint' => 'Receive a job offer', 'icon' => '🏆', 'points' => 100],
        ];
    }

    /**
     * Award anything newly reached; returns the new definitions (for toasts).
     *
     * @return Collection<int, array{key:string,title:string,hint:string,icon:string,points:int}>
     */
    public function evaluate(User $user): Collection
    {
        $earned = $user->achievements()->pluck('key')->flip();
        $ctx = $this->context($user);
        $new = collect();

        foreach (self::definitions() as $key => $definition) {
            if (isset($earned[$key]) || ! $this->reached($key, $ctx)) {
                continue;
            }

            Achievement::query()->firstOrCreate(
                ['user_id' => $user->id, 'key' => $key],
                ['points' => $definition['points'], 'earned_at' => now()],
            );

            $new->push(['key' => $key] + $definition);
        }

        return $new;
    }

    /** All milestones with earned state, for the dashboard grid. */
    public function board(User $user): array
    {
        $earned = $user->achievements()->get()->keyBy('key');
        $rows = [];
        foreach (self::definitions() as $key => $definition) {
            $row = $earned->get($key);
            $rows[] = ['key' => $key] + $definition + [
                'earned' => $row !== null,
                'earned_at' => $row?->earned_at,
                'new' => $row !== null && ! $row->seen,
            ];
        }

        $points = (int) $earned->sum('points');

        return [
            'items' => $rows,
            'points' => $points,
            'earned_count' => $earned->count(),
            'total_count' => count($rows),
            'level' => $this->level($points),
        ];
    }

    public function markSeen(User $user): void
    {
        $user->achievements()->where('seen', false)->update(['seen' => true]);
    }

    /** @return array{name:string,next:?string,next_at:?int,progress:int} */
    public function level(int $points): array
    {
        $levels = config('engagement.levels');
        $current = $levels[0];
        $next = null;
        foreach ($levels as $level) {
            if ($points >= $level['min']) {
                $current = $level;
            } elseif ($next === null) {
                $next = $level;
            }
        }

        $progress = $next
            ? (int) round(($points - $current['min']) / max(1, $next['min'] - $current['min']) * 100)
            : 100;

        return ['name' => $current['name'], 'next' => $next['name'] ?? null, 'next_at' => $next['min'] ?? null, 'progress' => max(0, min(100, $progress))];
    }

    /** Applications marked applied this week (Mon–Sun in the display timezone). */
    public function weeklyGoal(User $user): array
    {
        $tz = config('app.display_timezone', 'UTC');
        $start = Carbon::now($tz)->startOfWeek()->utc();
        $goal = (int) config('engagement.weekly_application_goal', 3);
        $count = $user->applications()->whereNotNull('applied_at')->where('applied_at', '>=', $start)->count();

        return ['goal' => $goal, 'count' => $count, 'progress' => min(100, (int) round($count / max(1, $goal) * 100)), 'met' => $count >= $goal];
    }

    private function context(User $user): array
    {
        $profile = $user->profile()->first();
        $skillIds = $profile ? $profile->skills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all() : [];
        $plans = $user->skillGapPlans()->get(['payload']);
        $planSkillIds = $plans->flatMap(fn ($p) => collect($p->payload['gaps'] ?? [])->pluck('skill.id'))->unique();
        $applications = $user->applications()->get(['status', 'applied_at']);

        return [
            'resumes' => $user->resumes()->exists(),
            'strength' => $this->strength->for($user)['score'],
            'skills' => count($skillIds),
            'preferences' => $user->jobPreference()->exists(),
            'best' => (int) (JobMatch::query()->where('user_id', $user->id)->eligible()->max('score') ?? 0),
            'threshold' => $this->settings->advisoryThreshold(),
            'plans' => $plans->count(),
            'gaps_closed' => $planSkillIds->intersect($skillIds)->count(),
            'saved' => $applications->count(),
            'applied' => $applications->whereNotNull('applied_at')->count(),
            'interviewed' => $applications->whereIn('status', [ApplicationStatus::Interviewing, ApplicationStatus::Offer])->count(),
            'offers' => $applications->where('status', ApplicationStatus::Offer)->count(),
        ];
    }

    private function reached(string $key, array $c): bool
    {
        return match ($key) {
            'resume_uploaded' => $c['resumes'],
            'profile_half' => $c['strength'] >= 50,
            'profile_full' => $c['strength'] >= 100,
            'skills_5' => $c['skills'] >= 5,
            'preferences_set' => $c['preferences'],
            'first_match' => $c['best'] >= $c['threshold'] && $c['best'] > 0,
            'plan_generated' => $c['plans'] > 0,
            'gap_closed' => $c['gaps_closed'] > 0,
            'first_saved' => $c['saved'] > 0,
            'first_applied' => $c['applied'] > 0,
            'applied_5' => $c['applied'] >= 5,
            'first_interview' => $c['interviewed'] > 0,
            'first_offer' => $c['offers'] > 0,
            default => false,
        };
    }
}
