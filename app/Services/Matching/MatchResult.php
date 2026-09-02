<?php

namespace App\Services\Matching;

/**
 * Outcome of scoring one listing for one candidate. Persisted verbatim
 * onto job_matches so the UI can explain the score line by line and
 * admins can retune weights without re-deriving anything.
 */
final class MatchResult
{
    /**
     * @param array<string, mixed> $breakdown see MatchScoringService for the shape
     * @param list<array{id:int,name:string,slug:string,required:bool}> $missingSkills
     */
    public function __construct(
        public readonly bool $eligible,
        public readonly ?string $ineligibilityReason,
        public readonly int $score,
        public readonly array $breakdown,
        public readonly array $missingSkills,
    ) {}

    public static function ineligible(string $reason): self
    {
        return new self(
            eligible: false,
            ineligibilityReason: $reason,
            score: 0,
            breakdown: ['components' => [], 'gaps' => [], 'summary' => $reason],
            missingSkills: [],
        );
    }

    /** Attributes for JobMatch::updateOrCreate(). */
    public function toAttributes(): array
    {
        return [
            'score' => $this->score,
            'score_breakdown' => $this->breakdown,
            'missing_skills' => $this->missingSkills,
            'is_eligible' => $this->eligible,
            'ineligibility_reason' => $this->ineligibilityReason,
            'computed_at' => now(),
        ];
    }
}
