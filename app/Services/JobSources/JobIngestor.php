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

    /** @return array{created: bool} */
    public function ingest(JobDto $dto): array
    {
        $industryId = $dto->industrySlug !== null
            ? Industry::query()->where('slug', $dto->industrySlug)->value('id')
            : null;

        $companyId = null;
        if ($dto->companyName !== null && $dto->companyName !== '') {
            $companyId = Company::query()->firstOrCreate(
                ['slug' => Str::slug($dto->companyName)],
                ['name' => $dto->companyName],
            )->id;
        }

        $listing = JobListing::query()->updateOrCreate(
            ['source' => $dto->source, 'source_job_id' => $dto->sourceJobId],
            [
                'title' => $dto->title,
                'company_name' => $dto->companyName,
                'company_id' => $companyId,
                'industry_id' => $industryId,
                'work_arrangement' => $dto->workArrangement,
                'employment_type' => $dto->employmentType,
                'location_text' => $dto->locationText,
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
                'salary_currency' => $dto->salaryCurrency,
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

        $this->syncSkills($listing, $dto);

        return ['created' => $listing->wasRecentlyCreated];
    }

    private function syncSkills(JobListing $listing, JobDto $dto): void
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

        if ($sync !== [] || $listing->skills()->exists()) {
            $listing->skills()->sync($sync);
        }
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
