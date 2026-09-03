<?php

namespace App\Livewire;

use App\Enums\EmploymentType;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use App\Jobs\SyncJobSourcesJob;
use App\Services\JobSources\FeedStatus;
use App\Services\SalaryFormatter;
use App\Support\Countries;
use Illuminate\Support\Collection;
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

    /** "TT", another ISO country code, or "remote" (remote-international, any employer country). */
    #[Url]
    public string $location = '';

    #[Url]
    public bool $eligibleOnly = true;

    public function mount(FeedStatus $feed): void
    {
        // Live feed: if the boards haven't been fetched recently, queue a sync now
        // (unique job; each board still honours its own rate-limit window).
        if ($feed->summary()['stale']) {
            SyncJobSourcesJob::dispatch();
        }

        // The feed opens wide on purpose: browsing everything is its job, while
        // Matches is the preference-driven view. Filters persist in the URL.
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'industry', 'arrangement', 'employment', 'location', 'eligibleOnly'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'industry', 'arrangement', 'employment', 'location');
        $this->eligibleOnly = true;
        $this->resetPage();
    }

    public function getIndustriesProperty(): Collection
    {
        return Industry::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Location choices from the countries that actually appear on open
     * listings: T&T first, then "remote from anywhere", then the rest by name.
     *
     * @return array<string, string> value => label
     */
    public function getLocationsProperty(): array
    {
        $codes = JobListing::query()->active()->whereNotNull('country')->distinct()->pluck('country')
            ->map(fn ($c) => strtoupper($c))
            ->reject(fn ($c) => $c === 'TT')
            ->mapWithKeys(fn ($c) => [$c => Countries::name($c)])
            ->sort()
            ->all();

        return ['TT' => 'Trinidad & Tobago (on-site / hybrid)', 'remote' => 'Remote — work from T&T for a foreign employer'] + $codes;
    }

    public function render(SalaryFormatter $salary, FeedStatus $feed)
    {
        $jobs = JobListing::query()
            ->active()
            ->with(['industry'])
            ->search($this->search)
            ->when($this->industry !== '', fn ($q) => $q->where('industry_id', (int) $this->industry))
            ->when($this->arrangement !== '', fn ($q) => $q->where('work_arrangement', $this->arrangement))
            ->when($this->employment !== '', fn ($q) => $q->where('employment_type', $this->employment))
            ->when($this->location === 'remote', fn ($q) => $q->where('work_arrangement', 'remote_international'))
            ->when($this->location !== '' && $this->location !== 'remote', fn ($q) => $q->where('country', strtoupper($this->location)))
            ->when($this->eligibleOnly, fn ($q) => $q->eligibleFromTT())
            ->orderByDesc('posted_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.job-list', [
            'jobs' => $jobs,
            'feed' => $feed->summary(),
            'salary' => $salary,
            'arrangements' => WorkArrangement::cases(),
            'employmentTypes' => EmploymentType::cases(),
        ]);
    }
}
