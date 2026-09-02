<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\ParseStatus;
use App\Models\JobMatch;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Home screen: live status cards + the single most useful next step.
 * All numbers come from the user's own rows; nothing here is computed
 * on the fly beyond counts.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SettingsService $settings): View
    {
        $user = $request->user();
        $profile = $user->profile()->first();
        $preference = $user->jobPreference()->first();
        $resume = $user->resumes()->latest()->first();
        $threshold = $settings->advisoryThreshold();

        $matches = JobMatch::query()->where('user_id', $user->id);
        $eligible = (clone $matches)->eligible();
        $best = (int) ((clone $eligible)->max('score') ?? 0);

        $applications = $user->applications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $plan = $user->skillGapPlans()->latest('generated_at')->latest('id')->first();
        $skillIds = $profile ? $profile->skills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all() : [];
        $planGaps = collect($plan?->payload['gaps'] ?? []);
        $planClosed = $planGaps->filter(fn (array $g) => in_array($g['skill']['id'], $skillIds, true))->count();

        $skillCount = count($skillIds);

        $steps = [
            'resume' => $resume !== null,
            'profile' => $profile !== null && $skillCount > 0,
            'preferences' => $preference !== null,
        ];

        return view('dashboard', [
            'resume' => $resume,
            'resumeProcessing' => $resume && in_array($resume->parse_status, [ParseStatus::Pending, ParseStatus::Processing], true),
            'profile' => $profile,
            'skillCount' => $skillCount,
            'preference' => $preference,
            'steps' => $steps,
            'eligibleCount' => (clone $eligible)->count(),
            'aboveThreshold' => (clone $eligible)->where('score', '>=', $threshold)->count(),
            'best' => $best,
            'threshold' => $threshold,
            'lastComputed' => (clone $matches)->max('computed_at'),
            'applications' => $applications,
            'applicationTotal' => $applications->sum(),
            'activeApplications' => (int) ($applications[ApplicationStatus::Applied->value] ?? 0) + (int) ($applications[ApplicationStatus::Interviewing->value] ?? 0),
            'plan' => $plan,
            'planGapCount' => $planGaps->count(),
            'planClosed' => $planClosed,
            'nextStep' => $this->nextStep($steps, $best, $threshold, (clone $eligible)->count()),
        ]);
    }

    /** @return array{title:string, body:string, route:string, cta:string} */
    private function nextStep(array $steps, int $best, int $threshold, int $eligible): array
    {
        return match (true) {
            ! $steps['resume'] && ! $steps['profile'] => [
                'title' => 'Upload your resume',
                'body' => 'We extract your skills, experience and qualifications privately, then you correct anything we got wrong.',
                'route' => 'resume.index', 'cta' => 'Upload resume',
            ],
            ! $steps['profile'] => [
                'title' => 'Check your profile',
                'body' => 'Add or confirm your skills — matching only counts skills that are on your profile.',
                'route' => 'profile.review', 'cta' => 'Review profile',
            ],
            ! $steps['preferences'] => [
                'title' => 'Set your job preferences',
                'body' => 'Target industry and work arrangement are 20% of every match score.',
                'route' => 'preferences.index', 'cta' => 'Set preferences',
            ],
            $eligible === 0 || $best < $threshold => [
                'title' => 'Follow your Skills Gap Plan',
                'body' => $eligible === 0
                    ? 'Nothing eligible scores yet — your plan ranks the skills that would open listings up, by effort.'
                    : "Your best match is {$best}; the plan shows which skills lift that above {$threshold} fastest.",
                'route' => 'plan.index', 'cta' => 'Open my plan',
            ],
            default => [
                'title' => 'Apply to your top matches',
                'body' => "You have listings scoring {$best}. Save the ones you like and track them as you apply.",
                'route' => 'matches.index', 'cta' => 'See matches',
            ],
        };
    }
}
