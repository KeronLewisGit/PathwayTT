<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Livewire\Concerns\AwardsAchievements;
use App\Models\Application;
use App\Services\Engagement\AchievementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Saved/applied jobs tracker (spec step 7): status pipeline with notes.
 */
class ApplicationTracker extends Component
{
    use AwardsAchievements;

    #[Url]
    public string $status = '';

    /** @var array<int, string> notes keyed by application id */
    public array $notes = [];

    public function mount(): void
    {
        $this->notes = Auth::user()->applications()->pluck('notes', 'id')
            ->map(fn ($note) => (string) $note)
            ->all();
    }

    public function setStatus(int $applicationId, string $status): void
    {
        $application = $this->find($applicationId);
        Gate::authorize('update', $application);

        try {
            $application->transitionTo(ApplicationStatus::from($status));
            $this->notify('Marked as '.strtolower($application->status->label()).'.', 'success');
            $this->awardAchievements();
        } catch (InvalidArgumentException $e) {
            $this->addError("status.{$applicationId}", $e->getMessage());
        }
    }

    public function saveNotes(int $applicationId): void
    {
        $application = $this->find($applicationId);
        Gate::authorize('update', $application);

        $this->validate(["notes.{$applicationId}" => ['nullable', 'string', 'max:2000']]);

        $application->update(['notes' => $this->notes[$applicationId] ?: null]);
        session()->flash("notes-saved-{$applicationId}", 'Notes saved.');
    }

    public function remove(int $applicationId): void
    {
        $application = $this->find($applicationId);
        Gate::authorize('delete', $application);

        $application->delete();
        unset($this->notes[$applicationId]);
    }

    private function find(int $applicationId): Application
    {
        return Application::query()->where('user_id', Auth::id())->findOrFail($applicationId);
    }

    public function render()
    {
        $user = Auth::user();

        $applications = $user->applications()
            ->with('jobListing.industry')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('updated_at')
            ->get();

        $counts = $user->applications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.application-tracker', [
            'applications' => $applications,
            'counts' => $counts,
            'statuses' => ApplicationStatus::cases(),
            'weekly' => app(AchievementService::class)->weeklyGoal($user),
        ]);
    }
}
