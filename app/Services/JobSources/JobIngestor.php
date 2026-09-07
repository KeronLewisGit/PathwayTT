<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Models\Company;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Normalizes JobDtos into job_listings, upserting on source + source_job_id.
 * Skills are resolved against the canonical taxonomy (slug, name, or alias)
 * — unresolvable skill terms are skipped, never invented.
 */
class JobIngestor
{
    /** @var array<string, int>|null lowercased term => skill id */
    private ?array $skillTerms = null;

    /** Column widths from the job_listings migration; boards routinely exceed them. */
    private const VARCHAR = 255;

    /**
     * @return array{created: bool, changed: bool} `changed` is true only when a
     *         column or the skill set actually differs, so hourly re-syncs of an
     *         unchanged feed do not fan out a recompute for every user.
     */
    public function ingest(JobDto $dto): array
    {
        $industryId = $dto->industrySlug !== null
            ? Industry::query()->where('slug', $dto->industrySlug)->value('id')
            : null;

        $companyName = self::fit($dto->companyName);
        $companyId = null;
        if ($companyName !== null && $companyName !== '') {
            // Str::slug() yields '' for non-Latin names (common on international
            // boards); without a fallback they would all collapse onto one row.
            $slug = Str::slug($companyName) ?: 'company-'.substr(sha1(mb_strtolower($companyName)), 0, 12);
            $companyId = Company::query()->firstOrCreate(['slug' => $slug], ['name' => $companyName])->id;
        }

        $currency = $dto->salaryCurrency !== null && preg_match('/^[A-Za-z]{3}$/', $dto->salaryCurrency)
            ? strtoupper($dto->salaryCurrency)
            : null;

        $listing = JobListing::query()->updateOrCreate(
            ['source' => $dto->source, 'source_job_id' => $dto->sourceJobId],
            [
                'title' => self::fit($dto->title),
                'company_name' => $companyName,
                'company_id' => $companyId,
                'industry_id' => $industryId,
                'work_arrangement' => $dto->workArrangement,
                'employment_type' => $dto->employmentType,
                'location_text' => self::fit($dto->locationText),
                'country' => $dto->country,
                'is_open_to_caribbean' => $dto->isOpenToCaribbean,
                'geo_eligibility' => $dto->geoEligibility,
                'required_overlap_hours' => $dto->requiredOverlapHours,
                'seniority' => $dto->seniority,
                'min_years_experience' => $dto->minYearsExperience,
                'min_education_level' => $dto->minEducationLevel,
                'requires_work_permit' => $dto->requiresWorkPermit,
                'required_credentials' => $dto->requiredCredentials ?: null,
                'salary_min_cents' => $dto->salaryMinCents,
                'salary_max_cents' => $dto->salaryMaxCents,
                'salary_currency' => $currency,
                'salary_period' => $dto->salaryPeriod,
                'description' => $dto->description,
                'requirements' => $dto->requirements ?: null,
                'posted_at' => $dto->postedAt,
                'closes_at' => $dto->closesAt,
                'apply_url' => $dto->applyUrl,
                'raw_payload' => $dto->rawPayload ?: null,
                'is_active' => true,
            ],
        );

        $skillsChanged = $this->syncSkills($listing, $dto);

        return [
            'created' => $listing->wasRecentlyCreated,
            'changed' => $listing->wasRecentlyCreated || $listing->wasChanged() || $skillsChanged,
        ];
    }

    /** Trim a value to a varchar(255) column, on a character boundary. */
    private static function fit(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > self::VARCHAR ? rtrim(mb_substr($value, 0, self::VARCHAR - 1)).'…' : $value;
    }

    /** @return bool whether the skill set differs from what was stored */
    private function syncSkills(JobListing $listing, JobDto $dto): bool
    {
        $sync = [];

        foreach ($dto->requiredSkills as $term) {
            if ($id = $this->resolveSkill($term)) {
                $sync[$id] = ['is_required' => true, 'weight' => 1];
            }
        }

        foreach ($dto->preferredSkills as $term) {
            if (($id = $this->resolveSkill($term)) && ! isset($sync[$id])) {
                $sync[$id] = ['is_required' => false, 'weight' => 1];
            }
        }

        // Compare explicitly: sync()'s "updated" list depends on the driver's
        // affected-row semantics (SQLite counts untouched rows, MySQL does not).
        $before = $listing->skills()->get()
            ->mapWithKeys(fn (Skill $s) => [(int) $s->id => (bool) $s->pivot->is_required])
            ->all();
        $after = array_map(fn (array $pivot) => (bool) $pivot['is_required'], $sync);
        ksort($before);
        ksort($after);

        if ($before === $after) {
            return false;
        }

        $listing->skills()->sync($sync);

        return true;
    }

    /** Resolve a skill term via slug, canonical name, or alias. */
    public function resolveSkill(string $term): ?int
    {
        if ($this->skillTerms === null) {
            $this->skillTerms = [];

            foreach (Skill::query()->get(['id', 'name', 'slug', 'aliases']) as $skill) {
                $this->skillTerms[$skill->slug] = $skill->id;

                foreach ($skill->matchTerms() as $matchTerm) {
                    $this->skillTerms[$matchTerm] ??= $skill->id;
                }
            }
        }

        $normalized = mb_strtolower(trim($term));

        return $this->skillTerms[$normalized]
            ?? $this->skillTerms[Str::slug($term)]
            ?? null;
    }
}
