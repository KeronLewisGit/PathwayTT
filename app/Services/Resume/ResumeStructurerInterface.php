<?php

namespace App\Services\Resume;

use App\DTOs\StructuredResume;

interface ResumeStructurerInterface
{
    /**
     * Turn raw resume text into structured profile data.
     * Implementations must be side-effect free: persistence (and the
     * never-overwrite-user-edits rules) live in ParseResumeJob.
     */
    public function structure(string $text): StructuredResume;
}
