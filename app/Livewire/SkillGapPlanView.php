<?php

namespace App\Livewire;

use App\Jobs\GenerateSkillGapPlanJob;
use App\Models\JobMatch;
use App\Models\SkillGapPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The Skills Gap Plan page: latest persisted plan with live progress
 * (gaps the user has since closed, best score then vs now), plus history.
 */
class SkillGapPlanView extends Component
{
    /** Unix timestamp of the last generate request, to drive the polling state. */
    public ?int $requestedAt = null;

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->profile()->exists() && ! $user->skillGapPlans()->exists()) {
            $this->generate();
        }
    }

    public function generate(): void
    {
        GenerateSkillGapPlanJob::dispatch((int) Auth::id());
        $this->requestedAt = now()->timestamp;
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
