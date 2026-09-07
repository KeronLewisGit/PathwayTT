<?php

namespace App\Livewire;

use App\Jobs\GenerateSkillGapPlanJob;
use App\Models\JobMatch;
use App\Models\SkillGapPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * The Skills Gap Plan page: latest persisted plan with live progress
 * (gaps the user has since closed, best score then vs now), plus history.
 */
class SkillGapPlanView extends Component
{
    /** Unix timestamp of the last generate request, to drive the polling state. */
    public ?int $requestedAt = null;

    /** Set when an inline generation failed, so the page explains instead of spinning forever. */
    public ?string $generateError = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->profile()->exists() && ! $user->skillGapPlans()->exists()) {
            $this->generate();
        }
    }

    public function generate(): void
    {
        $this->generateError = null;
        $userId = (int) Auth::id();

        if (! config('advisory.generate_inline')) {
            GenerateSkillGapPlanJob::dispatch($userId);
            $this->requestedAt = now()->timestamp;

            return;
        }

        // Shared hosting drains the queue only once a minute (if cron is healthy at
        // all), so build the plan inside this request: a few seconds, and the user
        // sees the result immediately instead of a spinner that depends on cron.
        try {
            app()->call([new GenerateSkillGapPlanJob($userId), 'handle']);
        } catch (Throwable $e) {
            report($e);
            $this->generateError = 'We could not build your plan just now. Please try again in a moment.';
        }

        $this->requestedAt = null;
    }

    public function render()
    {
        $user = Auth::user();
        $plan = $user->skillGapPlans()->latest('generated_at')->latest('id')->first();

        $generating = $this->requestedAt !== null
            && ($plan === null || $plan->generated_at->timestamp < $this->requestedAt);

        $profile = $user->profile()->first();
        $doneSkillIds = $profile ? $profile->skills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all() : [];

        $currentBest = (int) (JobMatch::query()->where('user_id', $user->id)->eligible()->max('score') ?? 0);

        return view('livewire.skill-gap-plan-view', [
            'plan' => $plan,
            'payload' => $plan?->payload ?? [],
            'generating' => $generating,
            'hasProfile' => $profile !== null,
            'doneSkillIds' => $doneSkillIds,
            'currentBest' => $currentBest,
            'history' => $user->skillGapPlans()->latest('generated_at')->latest('id')->limit(6)->get(),
        ]);
    }
}
