<?php

namespace App\Livewire;

use App\Enums\EvidenceSource;
use App\Enums\QualificationType;
use App\Jobs\RecomputeUserMatchesJob;
use App\Livewire\Concerns\AwardsAchievements;
use App\Models\Profile;
use App\Services\Engagement\ProfileStrength;
use App\Models\Skill;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The editable review screen. Parsing is never perfect: every extracted
 * field can be corrected here, and every edit marks the row is_user_edited
 * so re-parses never overwrite it (see ParseResumeJob::persist()).
 */
class ProfileReview extends Component
{
    use AwardsAchievements;

    // ── Profile scalars ─────────────────────────────────────────────
    public string $full_name = '';

    public string $phone = '';

    public string $region = '';

    public string $summary = '';

    public ?int $years_experience = null;

    public string $highest_education_level = '';

    public bool $has_nis = false;

    public bool $has_bir = false;

    public bool $has_drivers_permit = false;

    public bool $has_police_certificate = false;

    public bool $willing_to_relocate = false;

    public string $availability_date = '';

    // ── Skill add form ──────────────────────────────────────────────
    public string $skillSearch = '';

    // ── Editable rows keyed by model id ─────────────────────────────
    public array $workRows = [];

    public array $educationRows = [];

    public array $certificationRows = [];

    // ── New-row forms (work/education/certification) ────────────────
    public array $newWork = [];

    public array $newEducation = [];

    public array $newCertification = [];

    public const REGIONS = [
        'Port of Spain', 'San Fernando', 'Chaguanas', 'Arima', 'Point Fortin',
        'Couva', 'Sangre Grande', 'Princes Town', 'Rio Claro', 'Siparia',
        'Diego Martin', 'San Juan', 'Tunapuna', 'Penal', 'Tobago',
    ];

    public function mount(): void
    {
        $profile = $this->profile();

        $this->full_name = (string) $profile->full_name;
        $this->phone = (string) $profile->phone;
        $this->region = (string) $profile->region;
        $this->summary = (string) $profile->summary;
        $this->years_experience = $profile->years_experience;
        $this->highest_education_level = $profile->highest_education_level->value ?? '';
        $this->has_nis = (bool) $profile->has_nis;
        $this->has_bir = (bool) $profile->has_bir;
        $this->has_drivers_permit = (bool) $profile->has_drivers_permit;
        $this->has_police_certificate = (bool) $profile->has_police_certificate;
        $this->willing_to_relocate = (bool) $profile->willing_to_relocate;
        $this->availability_date = $profile->availability_date?->format('Y-m-d') ?? '';

        $this->resetNewRows();
        $this->hydrateRows();
    }

    private function hydrateRows(): void
    {
        $profile = $this->profile();

        $this->workRows = $profile->workHistories()->orderByDesc('started_at')->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'employer' => $row->employer,
                'title' => $row->title,
                'started_at' => $row->started_at?->format('Y-m-d') ?? '',
                'ended_at' => $row->ended_at?->format('Y-m-d') ?? '',
                'is_current' => $row->is_current,
                'description' => (string) $row->description,
            ]])->all();

        $this->educationRows = $profile->educations()->orderByDesc('completed_at')->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'institution' => $row->institution,
                'qualification_type' => $row->qualification_type->value ?? '',
                'field' => (string) $row->field,
                'completed_at' => $row->completed_at?->format('Y-m-d') ?? '',
            ]])->all();

        $this->certificationRows = $profile->certifications()->orderByDesc('issued_at')->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'name' => $row->name,
                'issuer' => (string) $row->issuer,
                'issued_at' => $row->issued_at?->format('Y-m-d') ?? '',
            ]])->all();
    }

    private function resetNewRows(): void
    {
        $this->newWork = ['employer' => '', 'title' => '', 'started_at' => '', 'ended_at' => '', 'is_current' => false, 'description' => ''];
        $this->newEducation = ['institution' => '', 'qualification_type' => '', 'field' => '', 'completed_at' => ''];
        $this->newCertification = ['name' => '', 'issuer' => '', 'issued_at' => ''];
    }

    public function profile(): Profile
    {
        return Profile::query()->firstOrCreate(['user_id' => Auth::id()]);
    }

    // ── Profile ─────────────────────────────────────────────────────

    public function saveProfile(): void
    {
        $this->validate([
            'full_name' => ['required', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'region' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:60'],
            'highest_education_level' => ['nullable', 'in:'.implode(',', array_column(QualificationType::cases(), 'value'))],
            'availability_date' => ['nullable', 'date'],
        ]);

        $this->profile()->update([
            'full_name' => $this->full_name,
            'phone' => $this->phone ?: null,
            'region' => $this->region ?: null,
            'summary' => $this->summary ?: null,
            'years_experience' => $this->years_experience,
            'highest_education_level' => $this->highest_education_level ?: null,
            'has_nis' => $this->has_nis,
            'has_bir' => $this->has_bir,
            'has_drivers_permit' => $this->has_drivers_permit,
            'has_police_certificate' => $this->has_police_certificate,
            'willing_to_relocate' => $this->willing_to_relocate,
            'availability_date' => $this->availability_date ?: null,
        ]);

        $this->queueRecompute();
        session()->flash('saved-profile', 'Profile saved.');
    }

    // ── Skills ──────────────────────────────────────────────────────

    public function getSkillSuggestionsProperty(): Collection
    {
        if (mb_strlen(trim($this->skillSearch)) < 2) {
            return collect();
        }

        $attached = $this->profile()->skills()->pluck('skills.id');
        $term = trim($this->skillSearch);

        return Skill::query()
            ->whereNotIn('id', $attached)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('aliases', 'like', "%{$term}%"))
            ->orderBy('name')
            ->limit(8)
            ->get();
    }

    public function addSkill(int $skillId): void
    {
        Skill::query()->findOrFail($skillId);

        $this->profile()->skills()->syncWithoutDetaching([
            $skillId => [
                'evidence_source' => EvidenceSource::SelfReported->value,
                'is_user_edited' => true,
            ],
        ]);

        $this->skillSearch = '';
        $this->queueRecompute();
    }

    public function updateSkillProficiency(int $skillId, int $proficiency): void
    {
        $this->profile()->skills()->updateExistingPivot($skillId, [
            'proficiency' => max(1, min(5, $proficiency)),
            'is_user_edited' => true,
        ]);
        $this->queueRecompute();
    }

    public function removeSkill(int $skillId): void
    {
        $this->profile()->skills()->detach($skillId);
        $this->queueRecompute();
    }

    // ── Work history ────────────────────────────────────────────────

    public function saveWorkHistory(int $id): void
    {
        $this->validate([
            "workRows.{$id}.employer" => ['required', 'string', 'max:190'],
            "workRows.{$id}.title" => ['required', 'string', 'max:190'],
            "workRows.{$id}.started_at" => ['nullable', 'date'],
            "workRows.{$id}.ended_at" => ['nullable', 'date'],
        ]);

        $data = $this->workRows[$id];

        $this->profile()->workHistories()->findOrFail($id)->update([
            'employer' => $data['employer'],
            'title' => $data['title'],
            'started_at' => $data['started_at'] ?: null,
            'ended_at' => $data['ended_at'] ?: null,
            'is_current' => (bool) $data['is_current'],
            'description' => $data['description'] ?: null,
            'is_user_edited' => true,
        ]);

        $this->queueRecompute();
        session()->flash('saved-work', 'Work history saved.');
    }

    public function addWorkHistory(): void
    {
        $this->validate([
            'newWork.employer' => ['required', 'string', 'max:190'],
            'newWork.title' => ['required', 'string', 'max:190'],
            'newWork.started_at' => ['nullable', 'date'],
            'newWork.ended_at' => ['nullable', 'date', 'after_or_equal:newWork.started_at'],
        ]);

        $this->profile()->workHistories()->create([
            ...collect($this->newWork)->map(fn ($value) => $value === '' ? null : $value)->all(),
            'is_user_edited' => true,
        ]);

        $this->resetNewRows();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    public function deleteWorkHistory(int $id): void
    {
        $this->profile()->workHistories()->findOrFail($id)->delete();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    // ── Education ───────────────────────────────────────────────────

    public function saveEducation(int $id): void
    {
        $this->validate([
            "educationRows.{$id}.institution" => ['required', 'string', 'max:190'],
            "educationRows.{$id}.qualification_type" => ['required', 'in:'.implode(',', array_column(QualificationType::cases(), 'value'))],
            "educationRows.{$id}.completed_at" => ['nullable', 'date'],
        ]);

        $data = $this->educationRows[$id];

        $this->profile()->educations()->findOrFail($id)->update([
            'institution' => $data['institution'],
            'qualification_type' => $data['qualification_type'],
            'field' => $data['field'] ?: null,
            'completed_at' => $data['completed_at'] ?: null,
            'is_user_edited' => true,
        ]);

        $this->queueRecompute();
        session()->flash('saved-education', 'Education saved.');
    }

    public function addEducation(): void
    {
        $this->validate([
            'newEducation.institution' => ['required', 'string', 'max:190'],
            'newEducation.qualification_type' => ['required', 'in:'.implode(',', array_column(QualificationType::cases(), 'value'))],
            'newEducation.completed_at' => ['nullable', 'date'],
        ]);

        $this->profile()->educations()->create([
            ...collect($this->newEducation)->map(fn ($value) => $value === '' ? null : $value)->all(),
            'is_user_edited' => true,
        ]);

        $this->resetNewRows();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    public function deleteEducation(int $id): void
    {
        $this->profile()->educations()->findOrFail($id)->delete();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    // ── Certifications ──────────────────────────────────────────────

    public function saveCertification(int $id): void
    {
        $this->validate([
            "certificationRows.{$id}.name" => ['required', 'string', 'max:190'],
            "certificationRows.{$id}.issued_at" => ['nullable', 'date'],
        ]);

        $data = $this->certificationRows[$id];

        $this->profile()->certifications()->findOrFail($id)->update([
            'name' => $data['name'],
            'issuer' => $data['issuer'] ?: null,
            'issued_at' => $data['issued_at'] ?: null,
            'is_user_edited' => true,
        ]);

        $this->queueRecompute();
        session()->flash('saved-certification', 'Certification saved.');
    }

    public function addCertification(): void
    {
        $this->validate([
            'newCertification.name' => ['required', 'string', 'max:190'],
            'newCertification.issued_at' => ['nullable', 'date'],
        ]);

        $this->profile()->certifications()->create([
            ...collect($this->newCertification)->map(fn ($value) => $value === '' ? null : $value)->all(),
            'is_user_edited' => true,
        ]);

        $this->resetNewRows();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    public function deleteCertification(int $id): void
    {
        $this->profile()->certifications()->findOrFail($id)->delete();
        $this->hydrateRows();
        $this->queueRecompute();
    }

    /**
     * Every profile edit re-ranks the user's matches in the background.
     * RecomputeUserMatchesJob is ShouldBeUnique, so a burst of edits
     * collapses into one queued run.
     */
    private function queueRecompute(): void
    {
        RecomputeUserMatchesJob::dispatch((int) Auth::id());
        $this->awardAchievements();
    }

    public function render()
    {
        $profile = $this->profile()->load(['skills', 'workHistories', 'educations', 'certifications']);

        return view('livewire.profile-review', [
            'strength' => app(ProfileStrength::class)->for(Auth::user()),
            'profile' => $profile,
            'qualificationTypes' => QualificationType::cases(),
        ]);
    }
}
