<?php

namespace App\Services\JobSources;

use App\DTOs\JobDto;
use App\Enums\EmploymentType;
use App\Enums\GeoEligibility;
use App\Enums\QualificationType;
use App\Enums\WorkArrangement;
use App\Support\Money;
use Illuminate\Support\Carbon;
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

            // Only title + work_arrangement are required; every other column may be
            // absent from a hand-made file and must read as blank, not crash.
            $row = array_fill_keys(self::COLUMNS, '');
            foreach ($header as $i => $column) {
                $row[$column] = isset($values[$i]) ? trim((string) $values[$i]) : '';
            }

            if (($row['title'] ?? '') === '') {
                continue;
            }

            // Enum and date columns are validated HERE, inside the generator, so a
            // bad cell fails this file (moved to failed/ with a line number) instead
            // of being written to the database, where the model's enum casts would
            // then throw on every page that loads the listing.
            $where = "{$filename} line {$lineNumber}";

            yield new JobDto(
                source: $this->key(),
                sourceJobId: $row['source_job_id'] !== '' ? $row['source_job_id']
                    : hash('sha1', $filename.'|'.$row['title'].'|'.($row['company'] ?? '')),
                title: $row['title'],
                companyName: $row['company'] ?: null,
                industrySlug: $row['industry_slug'] ?: null,
                workArrangement: self::enumValue(WorkArrangement::class, $row['work_arrangement'] ?: 'on_premises', 'work_arrangement', $where),
                employmentType: self::enumValue(EmploymentType::class, $row['employment_type'] ?: null, 'employment_type', $where),
                locationText: $row['location'] ?: null,
                country: $row['country'] !== '' ? strtoupper(substr($row['country'], 0, 2)) : null,
                geoEligibility: self::enumValue(GeoEligibility::class, $row['geo_eligibility'] ?: null, 'geo_eligibility', $where),
                requiredOverlapHours: $row['required_overlap_hours'] !== '' ? (int) $row['required_overlap_hours'] : null,
                seniority: $row['seniority'] ?: null,
                minYearsExperience: ($row['min_years_experience'] ?? '') !== '' ? (int) $row['min_years_experience'] : null,
                minEducationLevel: self::enumValue(QualificationType::class, ($row['min_education_level'] ?? '') !== '' ? strtolower($row['min_education_level']) : null, 'min_education_level', $where),
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
                postedAt: self::dateValue($row['posted_at'] ?: null, 'posted_at', $where),
                closesAt: self::dateValue($row['closes_at'] ?: null, 'closes_at', $where),
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

    /**
     * A backed-enum value for a CSV cell, or a clear error naming the file, line
     * and column. Accepted values are the enum's backing strings (e.g. "permanent").
     *
     * @template T of BackedEnum
     * @param class-string<T> $enum
     */
    private static function enumValue(string $enum, ?string $value, string $column, string $where): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', trim($value)));
        if ($enum::tryFrom($normalized) === null) {
            $allowed = implode(', ', array_map(fn (\BackedEnum $c) => $c->value, $enum::cases()));
            throw new RuntimeException("{$where}: {$column} \"{$value}\" is not one of: {$allowed}");
        }

        return $normalized;
    }

    /** An ISO date string for a CSV cell (accepts d/m/Y and Y-m-d), or a clear error. */
    private static function dateValue(?string $value, string $column, string $where): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($value));
            } catch (\Throwable) {
                continue;
            }

            // createFromFormat silently rolls "31/13/2026" over into 2027; only a
            // value that round-trips through the same format is a real date.
            if ($date->format($format) === trim($value)) {
                return $date->startOfDay()->toDateTimeString();
            }
        }

        throw new RuntimeException("{$where}: {$column} \"{$value}\" is not a date (use YYYY-MM-DD)");
    }
}
