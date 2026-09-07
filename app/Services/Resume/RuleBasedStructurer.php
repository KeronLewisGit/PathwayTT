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
     * Words a bullet point starts with and a job title never does. A line that
     * opens with one of these is an achievement, not a role.
     */
    private const BULLET_VERBS = 'performed|developed|planned|managed|assisted|provided|created|tutored|worked|handled|led|built|designed|maintained|supported|generated|conducted|coordinated|prepared|implemented|delivered|responsible|collaborated|ensured|organi[sz]ed|trained|oversaw|supervised|analy[sz]ed|reviewed|processed|monitored|reduced|increased|improved|achieved|completed|resolved|installed|configured|tested|wrote|produced|liaised|communicated|participated|contributed|helped|served|drove|launched|streamlined|negotiated|scheduled|recorded|reconciled|audited|assessed|researched|presented|taught|mentored|recruited|hired|advised|administered|operated|repaired|cleaned|cooked|sold|greeted';

    /** Employer-looking suffixes and institution words ("Tucker Energy Services Ltd."). */
    private const EMPLOYER_MARKERS = 'ltd|limited|inc|llc|plc|co|corp|corporation|company|group|holdings|bank|university|uwi|utt|costaatt|ministry|hospital|school|college|institute|services|solutions|technologies|systems|enterprises|associates|agency|authority|commission|board|foundation|trust|centre|center|store|restaurant|hotel|clinic|church|council|credit union|partners|consulting|industries|international|caribbean|trinidad|tobago|tstt|ngc|petrotrin|heritage|bptt|shell|republic|scotiabank|rbc|first citizens|massy|ansa|digicel|flow|unicomer|courts|nlcb|wasa|t&tec|ttpost|port authority|govt|government';

    /**
     * @return array<int, array{employer: string, title: string, started_at: ?string, ended_at: ?string, is_current: bool, description: ?string}>
     */
    public function parseExperience(string $section): array
    {
        if ($section === '') {
            return [];
        }

        // Line-based: a role starts at a line that looks like a role header (see
        // roleHeaderAt) and runs until the next one. Bullet points, sentences and
        // stray fragments can therefore never become a "job", whatever blank lines
        // the PDF extractor put around them.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $section)), fn (string $l) => $l !== ''));
        $entries = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $header = $this->roleHeaderAt($lines, $i);
            if ($header === null) {
                $i++;

                continue;
            }

            $j = $i + $header['lines_used'];
            $dates = $this->parseDateRange(implode(' ', array_slice($lines, $i, $header['lines_used'])));

            // A bare date line directly under the header belongs to it.
            if ($dates['start'] === null && isset($lines[$j]) && $this->isDateLine($lines[$j])) {
                $dates = $this->parseDateRange($lines[$j]);
                $j++;
            }

            $description = [];
            while ($j < $count && $this->roleHeaderAt($lines, $j) === null) {
                $description[] = ltrim($lines[$j], " \t•▪◦●○*-–—");
                $j++;
            }

            $entries[] = [
                'employer' => $header['employer'],
                'title' => $header['title'],
                'started_at' => $dates['start'],
                'ended_at' => $dates['end'],
                'is_current' => $dates['current'],
                'description' => $this->trimToLength(implode("\n", $description) ?: null, 2000),
            ];

            $i = $j;
        }

        return $entries;
    }

    /**
     * Is there a role header starting at line $i? Accepted shapes:
     *   "Title at Employer"            "Title — Employer"   "Title | Employer"
     *   "Title, Employer Ltd."          two lines: Title / Employer (either order)
     * plus, for the two-line shape, either a date range within the next two
     * lines or an employer-looking second line — real roles have one or both.
     *
     * @param list<string> $lines
     * @return ?array{title: string, employer: string, lines_used: int}
     */
    private function roleHeaderAt(array $lines, int $i): ?array
    {
        $first = $lines[$i] ?? '';
        if ($first === '' || $this->isBullet($first) || $this->isDateLine($first)) {
            return null;
        }

        $firstNoDate = $this->stripDates($first);
        if (! $this->isTitleLike($firstNoDate) && ! $this->isEmployerLike($firstNoDate)) {
            return null;
        }

        // "Title at Employer" / "Title @ Employer"
        if (preg_match('/^(?<title>.{2,80}?)\s+(?:at|@)\s+(?<employer>.{2,80})$/iu', $firstNoDate, $m)
            && $this->isTitleLike($m['title']) && $this->isTitleLike($m['employer'])) {
            return ['title' => trim($m['title']), 'employer' => trim($m['employer'], " ,."), 'lines_used' => 1];
        }

        // "Title — Employer" / "Title | Employer" / "Title, Employer Ltd."
        if (preg_match('/^(?<a>.{2,80}?)\s*(?<sep>—|–|\||,)\s*(?<b>.{2,80})$/u', $firstNoDate, $m)
            && $this->isTitleLike($m['a']) && $this->isTitleLike($m['b'])
            && ($m['sep'] !== ',' || $this->isEmployerLike($m['b']) || $this->isEmployerLike($m['a']))) {
            [$title, $employer] = $this->isEmployerLike($m['a']) && ! $this->isEmployerLike($m['b'])
                ? [$m['b'], $m['a']]
                : [$m['a'], $m['b']];

            return ['title' => trim($title), 'employer' => trim($employer, " ,."), 'lines_used' => 1];
        }

        // Two-line header: Title / Employer (or Employer / Title).
        $second = isset($lines[$i + 1]) ? $this->stripDates($lines[$i + 1]) : '';
        if ($second === '' || $this->isBullet($lines[$i + 1]) || ! $this->isTitleLike($second)) {
            return null;
        }

        $hasDate = $this->hasDateRange($lines[$i])
            || $this->hasDateRange($lines[$i + 1])
            || (isset($lines[$i + 2]) && $this->isDateLine($lines[$i + 2]));

        if (! $hasDate && ! $this->isEmployerLike($second) && ! $this->isEmployerLike($firstNoDate)) {
            return null; // two short lines with no date and no employer: not a role
        }

        [$title, $employer] = $this->isEmployerLike($firstNoDate) && ! $this->isEmployerLike($second)
            ? [$second, $firstNoDate]
            : [$firstNoDate, $second];

        return ['title' => trim($title), 'employer' => trim($employer, " ,."), 'lines_used' => 2];
    }

    /** Short, title-cased-ish, no sentence punctuation, does not open with an action verb. */
    private function isTitleLike(string $text): bool
    {
        $text = trim($text, " \t,;:-–—|()");
        if ($text === '' || mb_strlen($text) > 80 || str_word_count($text) > 8) {
            return false;
        }
        if (str_ends_with($text, '.') && ! preg_match('/\b(?:ltd|inc|co|corp)\.$/iu', $text)) {
            return false;
        }
        if (preg_match('/^(?:'.self::BULLET_VERBS.')\b/iu', $text)) {
            return false;
        }

        // Titles are Title Case or CAPS; a sentence fragment capitalises only its first word.
        $words = preg_split('/\s+/', $text) ?: [];
        $capitalised = count(array_filter($words, fn (string $w) => preg_match('/^[A-Z0-9(]/u', $w)));

        return $capitalised >= max(1, (int) ceil(count($words) / 2));
    }

    private function isEmployerLike(string $text): bool
    {
        return (bool) preg_match('/\b(?:'.self::EMPLOYER_MARKERS.')\b\.?/iu', $text);
    }

    private function isBullet(string $line): bool
    {
        return (bool) preg_match('/^(?:[•▪◦●○*\-–—]\s*|o\s+)/u', $line);
    }

    private function isDateLine(string $line): bool
    {
        return $this->hasDateRange($line) && mb_strlen(trim($this->stripDates($line), " \t|,()-–—")) <= 12;
    }

    private function hasDateRange(string $text): bool
    {
        return (bool) preg_match($this->dateRangePattern(), $text);
    }

    private function stripDates(string $text): string
    {
        $text = preg_replace($this->dateRangePattern(), '', $text) ?? $text;
        $text = preg_replace('/\(\s*\)/', '', $text) ?? $text;

        return trim($text, " \t•*-–—|,()");
    }

    /**
     * "2019 - Present", "Jan 2019 – Dec 2021", "July 1, 2021 – Present",
     * "01/2019 - 12/2021", "2019 to date".
     */
    private function dateRangePattern(): string
    {
        $months = self::MONTHS;
        $date = fn (string $name) => "(?:(?:{$months})\\.?\\s+(?:\\d{1,2}(?:st|nd|rd|th)?,?\\s+)?|\\d{1,2}\\s*[\\/.-]\\s*)?(?<{$name}>(?:19|20)\\d{2})";

        return '/'.$date('y1')."\\s*(?:-|–|—|to|until)\\s*(?:".$date('y2').'|(?<now>present|current|now|ongoing|to\\s*date|date))/iu';
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
