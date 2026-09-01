<?php

namespace App\Services\Resume;

use App\DTOs\StructuredResume;
use App\Enums\QualificationType;
use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Heuristic resume structurer. Default driver — must work with no API key.
 *
 * Strategy:
 *  1. Split the text into sections by common heading patterns.
 *  2. Match skills across the WHOLE text against the canonical taxonomy
 *     (name + aliases, word-boundary, case-insensitive).
 *  3. Parse experience blocks (title/employer + date ranges), education
 *     lines (institution + qualification keywords), certification lines.
 *
 * Output is intentionally conservative: a missed field is recoverable on
 * the review screen; a wrong guess erodes trust.
 */
class RuleBasedStructurer implements ResumeStructurerInterface
{
    /** Section keys => heading patterns (start of line, tolerant of colons). */
    private const SECTION_HEADINGS = [
        'experience' => '(?:work\s+experience|professional\s+experience|employment\s+(?:history|record)|work\s+history|experience|career\s+history)',
        'education' => '(?:education(?:al)?(?:\s+(?:background|history|qualifications?))?|academic\s+(?:background|qualifications?|history))',
        'skills' => '(?:(?:technical|key|core|relevant)\s+skills|skills(?:\s+(?:&|and)\s+(?:abilities|competencies|interests))?|competencies|areas\s+of\s+expertise)',
        'certifications' => '(?:certifications?|certificates?|licenses?\s*(?:&|and)?\s*certifications?|professional\s+(?:certifications?|development)|courses?\s+(?:&|and)\s+certifications?)',
        'summary' => '(?:professional\s+summary|career\s+(?:summary|objective)|summary|objective|profile|about\s+me)',
    ];

    private const MONTHS = 'jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?';

    public function structure(string $text): StructuredResume
    {
        $sections = $this->splitSections($text);
        $workHistories = $this->parseExperience($sections['experience'] ?? '');
        $educations = $this->parseEducation($sections['education'] ?? '');

        return new StructuredResume(
            fullName: $this->guessName($text),
            email: $this->matchFirst('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $text),
            phone: $this->guessPhone($text),
            summary: $this->trimToLength($sections['summary'] ?? null, 1000),
            yearsExperience: $this->estimateYearsExperience($workHistories),
            highestEducationLevel: $this->highestQualification($educations),
            skills: $this->matchSkills($text),
            workHistories: $workHistories,
            educations: $educations,
            certifications: $this->parseCertifications($sections['certifications'] ?? ''),
        );
    }

    /**
     * @return array<string, string> section key => body text
     */
    public function splitSections(string $text): array
    {
        $lines = explode("\n", $text);
        $sections = [];
        $current = 'preamble';
        $buffer = [];

        foreach ($lines as $line) {
            $heading = $this->headingKey($line);

            if ($heading !== null) {
                $sections[$current] = trim(implode("\n", $buffer));
                $buffer = [];
                $current = $heading;

                continue;
            }

            $buffer[] = $line;
        }

        $sections[$current] = trim(implode("\n", $buffer));

        return array_filter($sections, fn (string $body) => $body !== '');
    }

    /** Returns the section key if the line is a recognized heading. */
    private function headingKey(string $line): ?string
    {
        $candidate = trim($line, " \t:•-–—*#");

        // Headings are short lines, not sentences.
        if ($candidate === '' || mb_strlen($candidate) > 45) {
            return null;
        }

        foreach (self::SECTION_HEADINGS as $key => $pattern) {
            if (preg_match("/^{$pattern}\$/iu", $candidate)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Alias-aware skill matching over the entire text.
     *
     * @return array<int, array{skill_id: int, name: string}>
     */
    public function matchSkills(string $text): array
    {
        $found = [];

        foreach (Skill::query()->get(['id', 'name', 'slug', 'aliases']) as $skill) {
            foreach ($skill->matchTerms() as $term) {
                $escaped = preg_quote($term, '/');

                // Word-ish boundaries that survive terms like "C#", "C++",
                // ".NET": no letter/digit may directly touch the term.
                if (preg_match("/(?<![A-Za-z0-9]){$escaped}(?![A-Za-z0-9+#])/iu", $text)) {
                    $found[$skill->id] = ['skill_id' => $skill->id, 'name' => $skill->name];

                    break;
                }
            }
        }

        return array_values($found);
    }

    /**
     * @return array<int, array{employer: string, title: string, started_at: ?string, ended_at: ?string, is_current: bool, description: ?string}>
     */
    public function parseExperience(string $section): array
    {
        if ($section === '') {
            return [];
        }

        $entries = [];

        // Blocks are separated by blank lines; a block usually opens with a
        // "Title — Employer" or "Title at Employer" line plus a date range.
        foreach (preg_split("/\n{2,}/", $section) ?: [] as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $blockLines = array_values(array_filter(array_map('trim', explode("\n", $block))));
            $dates = $this->parseDateRange($block);
            $header = $this->parseRoleHeader($blockLines);

            if ($header === null) {
                continue;
            }

            $description = implode("\n", array_slice($blockLines, $header['lines_used']));
            // Drop the date line from the description if it is all that remains.
            $description = trim(preg_replace($this->dateRangePattern(), '', $description) ?? $description, " \n-–—|,");

            $entries[] = [
                'employer' => $header['employer'],
                'title' => $header['title'],
                'started_at' => $dates['start'],
                'ended_at' => $dates['end'],
                'is_current' => $dates['current'],
                'description' => $this->trimToLength($description ?: null, 2000),
            ];
        }

        return $entries;
    }

    /**
     * @param list<string> $blockLines
     * @return ?array{title: string, employer: string, lines_used: int}
     */
    private function parseRoleHeader(array $blockLines): ?array
    {
        if ($blockLines === []) {
            return null;
        }

        $first = preg_replace($this->dateRangePattern(), '', $blockLines[0]) ?? $blockLines[0];
        $first = trim($first, " \t•*-–—|,()");

        // "Title at Employer" / "Title — Employer" / "Title | Employer" / "Title, Employer"
        if (preg_match('/^(?<title>.{2,80}?)\s+(?:at|@)\s+(?<employer>.{2,80})$/iu', $first, $m)
            || preg_match('/^(?<title>.{2,80}?)\s*(?:—|–|\||,)\s*(?<employer>.{2,80})$/u', $first, $m)) {
            return ['title' => trim($m['title']), 'employer' => trim($m['employer']), 'lines_used' => 1];
        }

        // Two-line header: title on line 1, employer on line 2.
        if (isset($blockLines[1])) {
            $second = preg_replace($this->dateRangePattern(), '', $blockLines[1]) ?? $blockLines[1];
            $second = trim($second, " \t•*-–—|,()");

            if ($first !== '' && $second !== ''
                && mb_strlen($first) <= 80 && mb_strlen($second) <= 80
                && ! str_contains($first.$second, '@')) {
                return ['title' => $first, 'employer' => $second, 'lines_used' => 2];
            }
        }

        return null;
    }

    private function dateRangePattern(): string
    {
        $months = self::MONTHS;

        return "/(?:(?:{$months})\\.?\\s+)?(?<y1>(?:19|20)\\d{2})\\s*(?:-|–|—|to|until)\\s*(?:(?:(?:{$months})\\.?\\s+)?(?<y2>(?:19|20)\\d{2})|(?<now>present|current|now|to\\s*date))/iu";
    }

    /** @return array{start: ?string, end: ?string, current: bool} */
    public function parseDateRange(string $text): array
    {
        if (! preg_match($this->dateRangePattern(), $text, $m)) {
            return ['start' => null, 'end' => null, 'current' => false];
        }

        $current = ($m['now'] ?? '') !== '';

        return [
            'start' => $m['y1'].'-01-01',
            'end' => $current ? null : (($m['y2'] ?? '') !== '' ? $m['y2'].'-12-31' : null),
            'current' => $current,
        ];
    }

    /**
     * @param array<int, array{started_at: ?string, ended_at: ?string, is_current: bool}> $workHistories
     */
    private function estimateYearsExperience(array $workHistories): ?int
    {
        $years = [];

        foreach ($workHistories as $entry) {
            if ($entry['started_at'] !== null) {
                $start = (int) substr($entry['started_at'], 0, 4);
                $end = $entry['is_current'] || $entry['ended_at'] === null
                    ? (int) date('Y')
                    : (int) substr($entry['ended_at'], 0, 4);
                $years[] = [$start, max($start, $end)];
            }
        }

        if ($years === []) {
            return null;
        }

        // Span from earliest start to latest end (overlaps collapse naturally).
        $total = max(array_column($years, 1)) - min(array_column($years, 0));

        return max(0, min(50, $total));
    }

    /**
     * @return array<int, array{institution: string, qualification_type: string, field: ?string, completed_at: ?string}>
     */
    public function parseEducation(string $section): array
    {
        if ($section === '') {
            return [];
        }

        $qualificationPatterns = [
            'phd' => '/\b(?:ph\.?\s?d|doctorate|doctoral)\b/iu',
            'msc' => '/\b(?:m\.?\s?sc|master(?:\'?s)?(?:\s+(?:of|degree))?|mba|m\.?\s?a\.?(?=\s|$))\b/iu',
            'bsc' => '/\b(?:b\.?\s?sc|bachelor(?:\'?s)?(?:\s+(?:of|degree))?|b\.?\s?a\.?(?=\s|$)|beng|b\.?\s?eng)\b/iu',
            'associate' => '/\bassociate(?:\'?s)?\s+degree|\basc\b|\ba\.?a\.?s\.?\b/iu',
            'diploma' => '/\bdiploma\b/iu',
            'cape' => '/\bcape\b|\badvanced\s+proficiency\b/iu',
            'csec' => '/\bcsec\b|\bcxc\b|\bo[\'\s-]?levels?\b|\bgce\b/iu',
            'professional' => '/\bacca\b|\bcima\b|\bcpa\b|\bpmp\b|\bcfa\b/iu',
            'certificate' => '/\bcertificate\b/iu',
        ];

        $entries = [];

        foreach (preg_split("/\n{2,}/", $section) ?: [] as $block) {
            $blockText = trim($block);
            if ($blockText === '') {
                continue;
            }

            $qualification = null;
            foreach ($qualificationPatterns as $type => $pattern) {
                if (preg_match($pattern, $blockText)) {
                    $qualification = $type;

                    break;
                }
            }

            $institution = null;
            foreach (array_map('trim', explode("\n", $blockText)) as $line) {
                if (preg_match('/\b(?:university|college|institute|polytechnic|school|academy|campus|uwi|costaatt|sbcs|roytec|ytepp|nesc)\b/iu', $line)) {
                    $institution = trim($line, " \t•*-–—|,");

                    break;
                }
            }

            if ($qualification === null && $institution === null) {
                continue;
            }

            // Field of study: "BSc in Computer Science" / "Bachelor of Science"
            $field = $this->matchFirst('/\b(?:in|of)\s+([A-Z][A-Za-z&\/\s]{2,60}?)(?=\s*(?:,|\n|\(|$))/u', $blockText);

            $year = $this->matchFirst('/\b((?:19|20)\d{2})\b(?!\s*(?:-|–|to))/', $blockText);

            $entries[] = [
                'institution' => $this->trimToLength($institution ?? 'Unknown institution', 190) ?? 'Unknown institution',
                'qualification_type' => $qualification ?? QualificationType::Certificate->value,
                'field' => $this->trimToLength($field, 190),
                'completed_at' => $year !== null ? "{$year}-12-31" : null,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int, array{name: string, issuer: ?string, issued_at: ?string}>
     */
    public function parseCertifications(string $section): array
    {
        if ($section === '') {
            return [];
        }

        $entries = [];

        foreach (array_map('trim', explode("\n", $section)) as $line) {
            $line = trim($line, " \t•*-–—");
            if ($line === '' || mb_strlen($line) < 3) {
                continue;
            }

            $year = $this->matchFirst('/\b((?:19|20)\d{2})\b/', $line);
            $name = trim(preg_replace('/[(,\s-]*\b(?:19|20)\d{2}\b[),\s-]*/', ' ', $line) ?? $line, " ,-–—");

            // "Cert Name — Issuer" / "Cert Name, Issuer"
            $issuer = null;
            if (preg_match('/^(?<name>.{3,120}?)\s*(?:—|–|\||,)\s*(?<issuer>.{2,80})$/u', $name, $m)) {
                $name = trim($m['name']);
                $issuer = trim($m['issuer']);
            }

            if ($name === '') {
                continue;
            }

            $entries[] = [
                'name' => $this->trimToLength($name, 190) ?? $name,
                'issuer' => $this->trimToLength($issuer, 190),
                'issued_at' => $year !== null ? "{$year}-12-31" : null,
            ];
        }

        return $entries;
    }

    private function guessName(string $text): ?string
    {
        foreach (array_slice(explode("\n", $text), 0, 5) as $line) {
            $line = trim($line, " \t•*-–—");

            if ($line === '' || str_contains($line, '@') || preg_match('/\d/', $line)) {
                continue;
            }

            $words = preg_split('/\s+/', $line) ?: [];

            if (count($words) >= 2 && count($words) <= 5 && mb_strlen($line) <= 60
                && ! $this->headingKey($line)) {
                return Str::title(mb_strtolower($line));
            }
        }

        return null;
    }

    private function guessPhone(string $text): ?string
    {
        // T&T numbers: (868) 555-1234, 868-555-1234, +1 868 555 1234 — plus
        // generic international formats.
        return $this->matchFirst('/(?:\+?1[\s.-]?)?\(?8686?\)?[\s.-]?\d{3}[\s.-]?\d{4}|\+?\d{1,3}[\s.-]?\(?\d{2,4}\)?(?:[\s.-]?\d{2,4}){2,3}/', $text);
    }

    /**
     * @param array<int, array{qualification_type: string}> $educations
     */
    private function highestQualification(array $educations): ?string
    {
        $best = null;

        foreach ($educations as $education) {
            $type = QualificationType::tryFrom($education['qualification_type']);

            if ($type !== null && ($best === null || $type->rank() > $best->rank())) {
                $best = $type;
            }
        }

        return $best?->value;
    }

    private function matchFirst(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $m)) {
            return trim($m[1] ?? $m[0]);
        }

        return null;
    }

    private function trimToLength(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim($value) : null;

        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
