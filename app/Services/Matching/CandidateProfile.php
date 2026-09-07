<?php

namespace App\Services\Matching;

use App\Enums\QualificationType;
use App\Models\User;
use App\Services\JobSources\HtmlBoardSource;

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
     * @param list<string> $roleTerms canonical role terms from work-history titles and the summary (RoleVocabulary)
     * @param ?string $inferredIndustrySlug the industry the resume implies (dominant skill category, else the text)
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
        public readonly array $roleTerms = [],
        public readonly ?string $inferredIndustrySlug = null,
    ) {}

    public static function fromUser(User $user): self
    {
        $profile = $user->profile()->first();
        $pref = $user->jobPreference()->first();
        $skills = $profile ? $profile->skills()->get(['skills.id', 'skills.category']) : collect();

        $titles = $profile
            ? $profile->workHistories()->pluck('title')->filter(fn ($t) => self::looksLikeTitle((string) $t))->values()->all()
            : [];
        $roleText = implode(' . ', array_filter([...$titles, (string) $profile?->summary]));

        return new self(
            userId: $user->id,
            hasProfile: $profile !== null,
            skillIds: $skills->pluck('id')->map(fn ($id) => (int) $id)->all(),
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
            roleTerms: RoleVocabulary::terms($roleText),
            inferredIndustrySlug: self::inferIndustry($skills->pluck('category')->all(), $roleText),
        );
    }

    public function hasSkill(int $skillId): bool
    {
        return in_array($skillId, $this->skillIds, true);
    }

    /** What-if copy of this candidate with one more skill (gap-plan projections). */
    public function withSkill(int $skillId): self
    {
        if ($this->hasSkill($skillId)) {
            return $this;
        }

        return new self(
            userId: $this->userId,
            hasProfile: $this->hasProfile,
            skillIds: [...$this->skillIds, $skillId],
            yearsExperience: $this->yearsExperience,
            education: $this->education,
            preferredIndustryId: $this->preferredIndustryId,
            workArrangements: $this->workArrangements,
            employmentTypes: $this->employmentTypes,
            seniority: $this->seniority,
            credentials: $this->credentials,
            roleTerms: $this->roleTerms,
            inferredIndustrySlug: $this->inferredIndustrySlug,
        );
    }

    /**
     * The rule-based parser sometimes files a bullet point as a role
     * ("Performed maintenance on several high priority systems"). Sentences
     * are not titles, and must not feed the role vocabulary.
     */
    private static function looksLikeTitle(string $title): bool
    {
        $title = trim($title);

        return $title !== ''
            && str_word_count($title) <= 6
            && ! str_contains($title, '.')
            && ! preg_match('/^(performed|developed|planned|managed|assisted|provided|created|tutored|worked|handled|led|built|designed|maintained|supported|generated|conducted|coordinated|prepared)\b/i', $title);
    }

    /** @param list<string> $categories skill categories on the profile */
    private static function inferIndustry(array $categories, string $roleText): ?string
    {
        $map = config('matching.skill_category_industries', []);
        $generic = config('matching.generic_skill_categories', []);

        $counts = [];
        $specific = 0;
        foreach ($categories as $category) {
            if (! in_array($category, $generic, true) && isset($map[$category])) {
                $counts[$map[$category]] = ($counts[$map[$category]] ?? 0) + 1;
                $specific++;
            }
        }

        // One or two skills say little about a field; only a real cluster counts.
        if ($counts !== [] && $specific >= (int) config('matching.min_skills_for_inference', 3)) {
            arsort($counts);

            return (string) array_key_first($counts);
        }

        return $roleText !== '' ? HtmlBoardSource::industryFromTitle($roleText) : null;
    }
}
