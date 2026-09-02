<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Bulk import from CSV files (template: docs/job-import-template.csv).
 * Files land in the inbox (Filament upload or manual drop), get parsed
 * into JobDtos during job:sync, then move to the archive directory.
 */
class CsvImportSource implements JobSourceInterface
{
    /** Columns required in the header row. */
    public const REQUIRED_COLUMNS = ['title', 'work_arrangement'];

    /** All recognized columns, in template order. */
    public const COLUMNS = [
        'source_job_id', 'title', 'company', 'industry_slug', 'work_arrangement',
        'employment_type', 'location', 'country', 'geo_eligibility',
        'required_overlap_hours', 'seniority', 'min_years_experience',
        'min_education_level', 'requires_work_permit', 'required_credentials',
        'salary_min', 'salary_max',
        'salary_currency', 'salary_period', 'description', 'requirements',
        'required_skills', 'preferred_skills', 'posted_at', 'closes_at', 'apply_url',
    ];

    public function key(): string
    {
        return 'csv';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function notes(): ?string
    {
        return null;
    }

    public function fetch(): iterable
    {
        $disk = Storage::disk(config('jobsources.csv.disk'));
        $inbox = config('jobsources.csv.inbox');
        $archive = config('jobsources.csv.archive');

        foreach ($disk->files($inbox) as $file) {
            if (! str_ends_with(strtolower($file), '.csv')) {
                continue;
            }

            try {
                yield from $this->parse($disk->get($file), basename($file));
            } catch (\Throwable $e) {
                // Park the bad file so the next scheduled run doesn't trip on it again.
                $disk->move($file, config('jobsources.csv.failed').'/'.now()->format('Ymd_His').'_'.basename($file));

                throw $e;
            }

            $disk->move($file, $archive.'/'.now()->format('Ymd_His').'_'.basename($file));
        }
    }

    /** @return iterable<JobDto> */
    public function parse(string $csv, string $filename = 'upload.csv'): iterable
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return;
        }

        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);
                throw new RuntimeException("CSV {$filename} is missing required column \"{$required}\".");
            }
        }

        $lineNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if ($values === [null] || $values === false) {
                continue; // blank line
            }

            $row = [];
            foreach ($header as $i => $column) {
                $row[$column] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }

            if (($row['title'] ?? '') === '') {
                continue;
            }

            yield new JobDto(
                source: $this->key(),
                sourceJobId: $row['source_job_id'] !== '' ? $row['source_job_id']
                    : hash('sha1', $filename.'|'.$row['title'].'|'.($row['company'] ?? '')),
                title: $row['title'],
                companyName: $row['company'] ?: null,
                industrySlug: $row['industry_slug'] ?: null,
                workArrangement: $row['work_arrangement'] ?: 'on_premises',
                employmentType: $row['employment_type'] ?: null,
                locationText: $row['location'] ?: null,
                country: $row['country'] !== '' ? strtoupper(substr($row['country'], 0, 2)) : null,
                geoEligibility: $row['geo_eligibility'] ?: null,
                requiredOverlapHours: $row['required_overlap_hours'] !== '' ? (int) $row['required_overlap_hours'] : null,
                seniority: $row['seniority'] ?: null,
                minYearsExperience: ($row['min_years_experience'] ?? '') !== '' ? (int) $row['min_years_experience'] : null,
                minEducationLevel: ($row['min_education_level'] ?? '') !== '' ? strtolower($row['min_education_level']) : null,
                requiresWorkPermit: self::toBool($row['requires_work_permit'] ?? ''),
                requiredCredentials: self::splitList($row['required_credentials'] ?? ''),
                salaryMinCents: self::moneyToCents($row['salary_min'] ?? ''),
                salaryMaxCents: self::moneyToCents($row['salary_max'] ?? ''),
                salaryCurrency: $row['salary_currency'] !== '' ? strtoupper($row['salary_currency']) : null,
                salaryPeriod: $row['salary_period'] ?: null,
                description: $row['description'] ?: null,
                requirements: self::splitList($row['requirements'] ?? ''),
                requiredSkills: self::splitList($row['required_skills'] ?? ''),
                preferredSkills: self::splitList($row['preferred_skills'] ?? ''),
                postedAt: $row['posted_at'] ?: null,
                closesAt: $row['closes_at'] ?: null,
                applyUrl: $row['apply_url'] ?: null,
                rawPayload: ['file' => $filename, 'line' => $lineNumber],
            );
        }

        fclose($handle);
    }

    /** Delegates to App\Support\Money so dollars→cents lives in one place. */
    public static function moneyToCents(string $value): ?int
    {
        return Money::toCents($value);
    }

    /** "yes"/"true"/"1" → true, "no"/"false"/"0" → false, blank → null (unknown). */
    private static function toBool(string $value): ?bool
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'yes', 'y' => true,
            '0', 'false', 'no', 'n' => false,
            default => null,
        };
    }

    /** Pipe-separated list → trimmed array. */
    private static function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $value))));
    }
}
