<?php

namespace App\Livewire;

use App\Enums\EmploymentType;
use App\Enums\WorkArrangement;
use App\Jobs\RecomputeUserMatchesJob;
use App\Models\Industry;
use App\Models\JobListing;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Job preferences form (spec step 4). Preferences drive the default job
 * list filters now and match recomputation from Phase 4 onward.
 * Relocation + availability live on the profile but are edited here too
 * because users think of them as preferences.
 */
class JobPreferences extends Component
{
    public ?int $industry_id = null;

    /** @var list<string> WorkArrangement values */
    public array $work_arrangements = [];

    /** @var list<string> EmploymentType values */
    public array $employment_types = [];

    public string $seniority = '';

    /** Dollars as typed; converted to integer cents on save. */
    public string $min_salary = '';

    public string $min_salary_currency = 'TTD';

    public string $min_salary_period = 'monthly';

    public bool $willing_to_relocate = false;

    public string $availability_date = '';

    public function mount(): void
    {
        $user = Auth::user();
        // Query rather than read the relation: a cached null relation on the
        // shared user instance would otherwise survive a save.
        $pref = $user->jobPreference()->first();
        $profile = $user->profile()->first();

        if ($pref) {
            $this->industry_id = $pref->industry_id;
            $this->work_arrangements = $pref->work_arrangements ?? [];
            $this->employment_types = $pref->employment_types ?? [];
            $this->seniority = (string) $pref->seniority;
            $this->min_salary = $pref->min_salary_cents !== null ? Money::format($pref->min_salary_cents) : '';
            $this->min_salary_currency = $pref->min_salary_currency ?: 'TTD';
            $this->min_salary_period = $pref->min_salary_period ?: 'monthly';
        }

        if ($profile) {
            $this->willing_to_relocate = (bool) $profile->willing_to_relocate;
            $this->availability_date = $profile->availability_date?->format('Y-m-d') ?? '';
        }
    }

    protected function rules(): array
    {
        return [
            'industry_id' => ['nullable', 'integer', Rule::exists('industries', 'id')],
            'work_arrangements' => ['array'],
            'work_arrangements.*' => [Rule::enum(WorkArrangement::class)],
            'employment_types' => ['array'],
            'employment_types.*' => [Rule::enum(EmploymentType::class)],
            'seniority' => ['nullable', Rule::in(array_keys(JobListing::SENIORITIES))],
            'min_salary' => ['nullable', 'regex:/^[\d,]+(\.\d{1,2})?$/'],
            'min_salary_currency' => [Rule::in(['TTD', 'USD'])],
            'min_salary_period' => [Rule::in(['hourly', 'monthly', 'yearly'])],
            'willing_to_relocate' => ['boolean'],
            'availability_date' => ['nullable', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'min_salary.regex' => 'Enter the salary as a number, e.g. 8000 or 8,000.50.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();
        $minCents = Money::toCents($this->min_salary);

        $user->jobPreference()->updateOrCreate([], [
            'industry_id' => $this->industry_id ?: null,
            'work_arrangements' => array_values($this->work_arrangements),
            'employment_types' => array_values($this->employment_types),
            'seniority' => $this->seniority ?: null,
            'min_salary_cents' => $minCents,
            'min_salary_currency' => $minCents !== null ? $this->min_salary_currency : null,
            'min_salary_period' => $minCents !== null ? $this->min_salary_period : null,
        ]);

        $user->profile()->firstOrCreate([])->update([
            'willing_to_relocate' => $this->willing_to_relocate,
            'availability_date' => $this->availability_date ?: null,
        ]);

        // Preferences feed 20% of every score: re-rank in the background.
        RecomputeUserMatchesJob::dispatch($user->id);

        session()->flash('preferences-saved', 'Preferences saved.');
    }

    public function getIndustriesProperty(): Collection
    {
        return Industry::query()->orderBy('name')->get(['id', 'name']);
    }

    public function render()
    {
        return view('livewire.job-preferences', [
            'arrangements' => WorkArrangement::cases(),
            'employmentTypes' => EmploymentType::cases(),
            'seniorities' => JobListing::SENIORITIES,
        ]);
    }
}
