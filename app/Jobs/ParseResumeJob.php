<?php

namespace App\Jobs;

use App\DTOs\StructuredResume;
use App\Enums\EvidenceSource;
use App\Enums\ParseStatus;
use App\Models\Profile;
use App\Models\Resume;
use App\Services\Resume\ResumeStructurerInterface;
use App\Services\Resume\TextExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseResumeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Shared-host friendly: parsing a 5 MB file stays well inside this. */
    public int $timeout = 120;

    public function __construct(public Resume $resume) {}

    public function handle(TextExtractor $extractor, ResumeStructurerInterface $structurer): void
    {
        $this->resume->update(['parse_status' => ParseStatus::Processing]);

        $path = Storage::disk(config('resume.disk'))->path($this->resume->path);
        $text = $extractor->extract($path, $this->resume->mime_type);
        $structured = $structurer->structure($text);

        $this->persist($structured);

        $this->resume->update([
            'extracted_text' => $text,
            'parse_status' => ParseStatus::Parsed,
            'parse_error' => null,
            'parsed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->resume->update([
            'parse_status' => ParseStatus::Failed,
            'parse_error' => $exception?->getMessage() ?? 'Unknown parse failure',
        ]);
    }

    /**
     * Persist parsed data WITHOUT overwriting anything the user has set:
     *  - profile scalars fill only when currently empty;
     *  - child rows are inserted only when no matching row exists, and rows
     *    with is_user_edited = true are never touched;
     *  - skills attach via syncWithoutDetaching (existing pivots untouched).
     */
    private function persist(StructuredResume $structured): void
    {
        $profile = Profile::query()->firstOrCreate(['user_id' => $this->resume->user_id]);

        $profile->fill(array_filter([
            'full_name' => $profile->full_name ? null : $structured->fullName,
            'phone' => $profile->phone ? null : $structured->phone,
            'summary' => $profile->summary ? null : $structured->summary,
            'years_experience' => $profile->years_experience !== null ? null : $structured->yearsExperience,
            'highest_education_level' => $profile->highest_education_level ? null : $structured->highestEducationLevel,
        ], fn ($value) => $value !== null))->save();

        $profile->skills()->syncWithoutDetaching(
            collect($structured->skills)->mapWithKeys(fn (array $skill) => [
                $skill['skill_id'] => [
                    'evidence_source' => EvidenceSource::Resume->value,
                    'is_user_edited' => false,
                ],
            ])->all(),
        );

        foreach ($structured->workHistories as $entry) {
            $profile->workHistories()->firstOrCreate(
                ['employer' => $entry['employer'], 'title' => $entry['title']],
                [
                    'started_at' => $entry['started_at'],
                    'ended_at' => $entry['ended_at'],
                    'is_current' => $entry['is_current'],
                    'description' => $entry['description'],
                    'is_user_edited' => false,
                ],
            );
        }

        foreach ($structured->educations as $entry) {
            $profile->educations()->firstOrCreate(
                ['institution' => $entry['institution'], 'qualification_type' => $entry['qualification_type']],
                [
                    'field' => $entry['field'],
                    'completed_at' => $entry['completed_at'],
                    'is_user_edited' => false,
                ],
            );
        }

        foreach ($structured->certifications as $entry) {
            $profile->certifications()->firstOrCreate(
                ['name' => $entry['name']],
                [
                    'issuer' => $entry['issuer'],
                    'issued_at' => $entry['issued_at'],
                    'is_user_edited' => false,
                ],
            );
        }
    }
}
