<?php

namespace App\DTOs;

/**
 * Normalized job record produced by every JobSourceInterface adapter.
 * The ingestor maps this onto job_listings, resolving industry + skills.
 */
class JobDto
{
    public function __construct(
        public string $source,
        public ?string $sourceJobId,
        public string $title,
        public ?string $companyName = null,
        /** Industry slug from the seeded taxonomy (e.g. "ict-software"). */
        public ?string $industrySlug = null,
        /** App\Enums\WorkArrangement value. */
        public string $workArrangement = 'on_premises',
        /** App\Enums\EmploymentType value or null. */
        public ?string $employmentType = null,
        public ?string $locationText = null,
        /** ISO-3166 alpha-2. */
        public ?string $country = null,
        public ?bool $isOpenToCaribbean = null,
        /** App\Enums\GeoEligibility value or null. */
        public ?string $geoEligibility = null,
        public ?int $requiredOverlapHours = null,
        public ?string $seniority = null,
        /** Structured requirements used by matching (all optional). */
        public ?int $minYearsExperience = null,
        /** App\Enums\QualificationType value or null. */
        public ?string $minEducationLevel = null,
        public ?bool $requiresWorkPermit = null,
        /** @var list<string> nis|bir|drivers_permit|police_certificate */
        public array $requiredCredentials = [],
        public ?int $salaryMinCents = null,
        public ?int $salaryMaxCents = null,
        public ?string $salaryCurrency = null,
        public ?string $salaryPeriod = null,
        public ?string $description = null,
        /** @var list<string> free-text requirement lines */
        public array $requirements = [],
        /** @var list<string> required skills, by taxonomy slug/name/alias */
        public array $requiredSkills = [],
        /** @var list<string> nice-to-have skills, same matching */
        public array $preferredSkills = [],
        public ?string $postedAt = null,
        public ?string $closesAt = null,
        public ?string $applyUrl = null,
        /** @var array<string, mixed> raw adapter payload for debugging */
        public array $rawPayload = [],
    ) {}
}
