<?php

namespace App\Livewire;

use App\Enums\EmploymentType;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use App\Services\SalaryFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Browseable list of open listings with filters. Defaults come from the
 * user's saved preferences; every row links out to the original posting.
 * Phase 4 replaces "newest first" with the ranked match list.
 */
class JobList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $industry = '';

    #[Url]
    public string $arrangement = '';

    #[Url]
    public string $employment = '';

    #[Url]
    public bool $eligibleOnly = true;

    public function mount(): void
    {
        $pref = Auth::user()?->jobPreference()->first();

        if (! $pref) {
            return;
        }

        // Pre-fill from preferences only when the URL carries no explicit filter.
        if ($this->industry === '' && $pref->industry_id) {
            $this->industry = (string) $pref->industry_id;
        }

        if ($this->arrangement === '' && count($pref->work_arrangements ?? []) === 1) {
            $this->arrangement = $pref->work_arrangements[0];
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'industry', 'arrangement', 'employment', 'eligibleOnly'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'industry', 'arrangement', 'employment');
        $this->eligibleOnly = true;
        $this->resetPage();
    }

    public function getIndustriesProperty(): Collection
    {
        return Industry::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render(SalaryFormatter $salary)
    {
        $jobs = JobListing::query()
            ->active()
            ->with(['industry'])
            ->search($this->search)
            ->when($this->industry !== '', fn ($q) => $q->where('industry_id', (int) $this->industry))
            ->when($this->arrangement !== '', fn ($q) => $q->where('work_arrangement', $this->arrangement))
            ->when($this->employment !== '', fn ($q) => $q->where('employment_type', $this->employment))
            ->when($this->eligibleOnly, fn ($q) => $q->eligibleFromTT())
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.job-list', [
            'jobs' => $jobs,
            'salary' => $salary,
            'arrangements' => WorkArrangement::cases(),
            'employmentTypes' => EmploymentType::cases(),
        ]);
    }
}
