<?php

namespace App\Services\Advisory;

use App\Models\User;

interface SkillGapAnalyzerInterface
{
    /**
     * Build the skills-gap plan payload for a user from live listings and
     * the learning-resource catalog. See SkillGapAnalyzer for the shape.
     *
     * @return array<string, mixed>
     */
    public function analyze(User $user): array;
}
