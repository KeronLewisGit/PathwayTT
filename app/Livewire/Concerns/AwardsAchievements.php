<?php

namespace App\Livewire\Concerns;

use App\Services\Engagement\AchievementService;
use Illuminate\Support\Facades\Auth;

/**
 * Call after any state change a user makes; newly reached milestones are
 * announced as toasts (the layout listens for the "notify" event).
 */
trait AwardsAchievements
{
    protected function awardAchievements(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        foreach (app(AchievementService::class)->evaluate($user) as $achievement) {
            $this->dispatch('notify',
                message: "Milestone unlocked: {$achievement['title']} (+{$achievement['points']} points)",
                tone: 'celebrate',
            );
        }
    }

    protected function notify(string $message, string $tone = 'info'): void
    {
        $this->dispatch('notify', message: $message, tone: $tone);
    }
}
