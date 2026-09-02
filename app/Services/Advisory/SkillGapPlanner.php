<?php

namespace App\Services\Advisory;

use App\Models\SkillGapPlan;
use App\Models\User;
use App\Services\Engagement\AchievementService;

/**
 * Runs the analyzer and persists the result as a new plan version, so the
 * user can revisit it and compare against later ones as they add skills.
 */
class SkillGapPlanner
{
    public function __construct(private readonly SkillGapAnalyzerInterface $analyzer) {}

    public function generate(User $user): SkillGapPlan
    {
        $payload = $this->analyzer->analyze($user);

        $plan = SkillGapPlan::query()->create([
            'user_id' => $user->id,
            'target_industry_id' => $payload['scope']['industry_id'] ?? null,
            'generated_at' => now(),
            'payload' => $payload,
        ]);

        app(AchievementService::class)->evaluate($user);

        return $plan;
    }
}
