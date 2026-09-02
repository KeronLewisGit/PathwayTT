<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Advisory\SkillGapPlanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates a Skills Gap Plan in the background: re-scoring a few hundred
 * listings per candidate skill is cheap but not request-cycle cheap.
 */
class GenerateSkillGapPlanJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $userId) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(SkillGapPlanner $planner): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $planner->generate($user);
    }
}
