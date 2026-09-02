<?php

namespace App\Services\Matching;

use App\Enums\QualificationType;
use App\Models\User;

/**
 * Immutable snapshot of everything the scorer needs about a user, built
 * once per recompute so scoring N listings costs no further queries.
 */
final class CandidateProfile
{
    /**
     * @param list<int> $skillIds canonical skill ids on the profile
     * @param list<string> $workArrangements WorkArrangement values the user accepts
     * @param list<string> $employmentTypes EmploymentType values the user accepts
     * @param array<string, bool> $credentials nis|bir|drivers_permit|police_certificate => held
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $hasProfile,
        public readonly array $skillIds,
        public readonly ?int $yearsExperience,
        public readonly ?QualificationType $education,
        public readonly ?int $preferredIndustryId,
        public readonly array $workArrangements,
        public readonly array $employmentTypes,
        public readonly ?string $seniority,
        public readonly array $credentials,
    ) {}

    public static function fromUser(User $user): self
    {
        $profile = $user->profile()->first();
        $pref = $user->jobPreference()->first();

        return new self(
            userId: $user->id,
            hasProfile: $profile !== null,
            skillIds: $profile ? $profile->skills()->pluck('skills.id')->map(fn ($id) => (int) $id)->all() : [],
            yearsExperience: $profile?->years_experience,
            education: $profile?->highest_education_level,
            preferredIndustryId: $pref?->industry_id,
            workArrangements: $pref?->work_arrangements ?? [],
            employmentTypes: $pref?->employment_types ?? [],
            seniority: $pref?->seniority,
            credentials: [
                'nis' => (bool) $profile?->has_nis,
                'bir' => (bool) $profile?->has_bir,
                'drivers_permit' => (bool) $profile?->has_drivers_permit,
                'police_certificate' => (bool) $profile?->has_police_certificate,
            ],
        );
    }

    public function hasSkill(int $skillId): bool
    {
        return in_array($skillId, $this->skillIds, true);
    }
}
