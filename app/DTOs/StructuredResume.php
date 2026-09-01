<?php

namespace App\DTOs;

/**
 * Normalized output of any ResumeStructurerInterface implementation.
 * All fields are nullable/empty-able: structurers report only what they
 * confidently found, and the persistence layer never overwrites
 * user-edited data with parser output.
 */
class StructuredResume
{
    public function __construct(
        public ?string $fullName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $summary = null,
        public ?int $yearsExperience = null,
        /** QualificationType value, e.g. "bsc". */
        public ?string $highestEducationLevel = null,
        /** @var array<int, array{skill_id: int, name: string}> */
        public array $skills = [],
        /** @var array<int, array{employer: string, title: string, started_at: ?string, ended_at: ?string, is_current: bool, description: ?string}> */
        public array $workHistories = [],
        /** @var array<int, array{institution: string, qualification_type: string, field: ?string, completed_at: ?string}> */
        public array $educations = [],
        /** @var array<int, array{name: string, issuer: ?string, issued_at: ?string}> */
        public array $certifications = [],
    ) {}
}
