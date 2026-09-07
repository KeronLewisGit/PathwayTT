<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Livewire\Concerns\AwardsAchievements;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Compact save / status control shown on match rows and the job page.
 * Applying itself always happens on the employer's site (link-out model);
 * this only tracks saved → applied → interviewing → offer / rejected.
 */
class JobTrackButton extends Component
{
    use AwardsAchievements;

    #[Locked]
    public int $jobListingId;

    public function save(): void
    {
        $application = Application::query()->firstOrCreate(
            ['user_id' => Auth::id(), 'job_listing_id' => $this->jobListingId],
            ['status' => ApplicationStatus::Saved],
        );

        if ($application->wasRecentlyCreated) {
            $this->notify('Saved to your tracker.', 'success');
        }
        $this->awardAchievements();
    }

    public function setStatus(string $status): void
    {
        $application = $this->application();

        if ($application === null) {
            return;
        }

        Gate::authorize('update', $application);

        try {
            $application->transitionTo(ApplicationStatus::tryFrom($status) ?? throw new InvalidArgumentException('Unknown status.'));
            $this->notify('Marked as '.strtolower($application->status->label()).'.', 'success');
            $this->awardAchievements();
        } catch (InvalidArgumentException $e) {
            $this->addError('status', $e->getMessage());
        }
    }

    public function remove(): void
    {
        $application = $this->application();

        if ($application === null) {
            return;
        }

        Gate::authorize('delete', $application);
        $application->delete();
    }

    private function application(): ?Application
    {
        return Application::query()
            ->where('user_id', Auth::id())
            ->where('job_listing_id', $this->jobListingId)
            ->first();
    }

    public function render()
    {
        return view('livewire.job-track-button', [
            'application' => $this->application(),
        ]);
    }
}
