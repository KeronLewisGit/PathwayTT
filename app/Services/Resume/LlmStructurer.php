<?php

namespace App\Services\Resume;

use Anthropic\Client;
use App\DTOs\StructuredResume;
use App\Enums\QualificationType;
use App\Services\Skills\SkillMatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resume structuring via the Anthropic API (RESUME_PARSER_DRIVER=llm).
 *
 * The model is asked for JSON that must satisfy a strict schema
 * (output_config json_schema) — no prose, no invented fields. The response
 * is then validated AGAIN here before anything is returned, skills are
 * resolved to the canonical taxonomy (unknown names are dropped, never
 * created), and on any failure — API error, refusal, malformed JSON — the
 * rule-based structurer takes over so an upload never fails because of
 * the LLM. Persistence and the never-overwrite-user-edits rules stay in
 * ParseResumeJob.
 */
class LlmStructurer implements ResumeStructurerInterface
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You extract structured data from a job seeker's resume for an employment-assistance service in Trinidad and Tobago.

Return only the JSON object the schema describes. Rules:
- Use only information present in the resume. Never invent employers, dates, qualifications or skills. Leave a field null or an array empty when the resume does not state it.
- full_name is the candidate's name as written. email and phone are the first contact details found; phone may be a Trinidad and Tobago number written with or without the +1 868 prefix — keep it as written.
- summary: the candidate's own summary/objective if present, otherwise null. Do not write one.
- years_experience: total years of paid work experience the resume supports, as an integer; null if it cannot be worked out.
- highest_education_level: the highest completed qualification, mapped to exactly one of csec (CXC/CSEC/O-Levels), cape (CAPE/A-Levels), certificate, diploma, associate, bsc (any bachelor's degree), msc (any master's degree), phd, professional (ACCA, CIMA, PMP and similar professional qualifications); null if none stated.
- skills: concrete, individual skills and tools as short names (e.g. "Microsoft Excel", "Welding", "Customer Service", "PHP"). Do not include soft descriptions of the person, job titles or company names.
- work_histories: one entry per role, most recent first. Dates as YYYY-MM-DD, YYYY-MM or YYYY; is_current true when the role is ongoing ("present"). description is the resume's own wording, trimmed.
- educations: one entry per qualification with the institution as written; qualification_type uses the same values as highest_education_level; field is the subject/major if given.
- certifications: named certificates, licences and short courses with issuer and date when given.
PROMPT;

    public function __construct(
        private readonly Client $client,
        private readonly SkillMatcher $skills,
        private readonly RuleBasedStructurer $fallback,
    ) {}

    public function structure(string $text): StructuredResume
    {
        $text = $this->bounded($text);

        try {
            $json = $this->complete($text);
        } catch (Throwable $e) {
            Log::warning('LlmStructurer: API call failed; using rule-based parser', ['error' => $e->getMessage()]);

            return $this->fallback->structure($text);
        }

        if ($json === null) {
            Log::warning('LlmStructurer: model returned no usable output; using rule-based parser');

            return $this->fallback->structure($text);
        }

        $data = json_decode($json, true);
        if (! is_array($data) || ! $this->valid($data)) {
            Log::warning('LlmStructurer: response failed schema validation; using rule-based parser');

            return $this->fallback->structure($text);
        }

        return $this->toResume($data, $text);
    }

    /**
     * One request → the JSON text, or null on refusal / no text block.
     * Kept separate so tests can stub the network without an API key.
     *
     * @throws Throwable on transport/API errors
     */
    protected function complete(string $text): ?string
    {
        $message = $this->client->messages->create(
            model: config('resume.anthropic.model', 'claude-opus-5'),
            maxTokens: (int) config('resume.anthropic.max_tokens', 8000),
            system: self::SYSTEM_PROMPT,
            messages: [
                ['role' => 'user', 'content' => "Resume text:\n\n<resume>\n{$text}\n</resume>"],
            ],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => self::schema()]],
        );

        // Safety classifiers can decline a request (HTTP 200, stop_reason "refusal").
        if ($message->stopReason === 'refusal') {
            Log::notice('LlmStructurer: request refused', ['category' => $message->stopDetails?->category]);

            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return null;
    }

    /** JSON schema the model must satisfy (also used for local validation). */
    public static function schema(): array
    {
        $levels = array_map(fn (QualificationType $t) => $t->value, QualificationType::cases());
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['full_name', 'email', 'phone', 'summary', 'years_experience', 'highest_education_level', 'skills', 'work_histories', 'educations', 'certifications'],
            'properties' => [
                'full_name' => $nullableString,
                'email' => $nullableString,
                'phone' => $nullableString,
                'summary' => $nullableString,
                'years_experience' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 60],
                'highest_education_level' => ['type' => ['string', 'null'], 'enum' => [...$levels, null]],
                'skills' => ['type' => 'array', 'items' => ['type' => 'string']],
                'work_histories' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['employer', 'title', 'started_at', 'ended_at', 'is_current', 'description'],
                        'properties' => [
                            'employer' => ['type' => 'string'],
                            'title' => ['type' => 'string'],
                            'started_at' => $nullableString,
                            'ended_at' => $nullableString,
                            'is_current' => ['type' => 'boolean'],
                            'description' => $nullableString,
                        ],
                    ],
                ],
                'educations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['institution', 'qualification_type', 'field', 'completed_at'],
                        'properties' => [
                            'institution' => ['type' => 'string'],
                            'qualification_type' => ['type' => 'string', 'enum' => $levels],
                            'field' => $nullableString,
                            'completed_at' => $nullableString,
                        ],
                    ],
                ],
                'certifications' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'issuer', 'issued_at'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'issuer' => $nullableString,
                            'issued_at' => $nullableString,
                        ],
                    ],
                ],
            ],
        ];
    }

    /** Defensive validation of the decoded response — the schema is a request, this is the check. */
    private function valid(array $data): bool
    {
        foreach (['full_name', 'email', 'phone', 'summary'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && ! is_string($data[$key])) {
                return false;
            }
        }

        if (isset($data['years_experience']) && (! is_int($data['years_experience']) || $data['years_experience'] < 0 || $data['years_experience'] > 60)) {
            return false;
        }

        if (isset($data['highest_education_level']) && QualificationType::tryFrom((string) $data['highest_education_level']) === null) {
            return false;
        }

        foreach (['skills', 'work_histories', 'educations', 'certifications'] as $key) {
            if (! is_array($data[$key] ?? [])) {
                return false;
            }
        }

        foreach ($data['work_histories'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['employer'] ?? null) || ! is_string($row['title'] ?? null)) {
                return false;
            }
        }

        foreach ($data['educations'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['institution'] ?? null) || QualificationType::tryFrom((string) ($row['qualification_type'] ?? '')) === null) {
                return false;
            }
        }

        foreach ($data['certifications'] ?? [] as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function toResume(array $data, string $text): StructuredResume
    {
        // Skills: model-named skills resolved through the taxonomy (aliases
        // included), unioned with a plain text match so nothing obvious is lost.
        $skills = $this->skills->matchTerms(array_filter($data['skills'] ?? [], 'is_string'))
            + $this->skills->match($text);

        $work = [];
        foreach ($data['work_histories'] ?? [] as $row) {
            if (trim($row['employer']) === '' || trim($row['title']) === '') {
                continue;
            }
            $work[] = [
                'employer' => mb_substr(trim($row['employer']), 0, 190),
                'title' => mb_substr(trim($row['title']), 0, 190),
                'started_at' => self::date($row['started_at'] ?? null),
                'ended_at' => ($row['is_current'] ?? false) ? null : self::date($row['ended_at'] ?? null),
                'is_current' => (bool) ($row['is_current'] ?? false),
                'description' => is_string($row['description'] ?? null) ? mb_substr(trim($row['description']), 0, 2000) ?: null : null,
            ];
        }

        $educations = [];
        foreach ($data['educations'] ?? [] as $row) {
            if (trim($row['institution']) === '') {
                continue;
            }
            $educations[] = [
                'institution' => mb_substr(trim($row['institution']), 0, 190),
                'qualification_type' => $row['qualification_type'],
                'field' => is_string($row['field'] ?? null) ? mb_substr(trim($row['field']), 0, 190) ?: null : null,
                'completed_at' => self::date($row['completed_at'] ?? null),
            ];
        }

        $certifications = [];
        foreach ($data['certifications'] ?? [] as $row) {
            if (trim($row['name']) === '') {
                continue;
            }
            $certifications[] = [
                'name' => mb_substr(trim($row['name']), 0, 190),
                'issuer' => is_string($row['issuer'] ?? null) ? mb_substr(trim($row['issuer']), 0, 190) ?: null : null,
                'issued_at' => self::date($row['issued_at'] ?? null),
            ];
        }

        $highest = $data['highest_education_level'] ?? null;
        if ($highest === null && $educations !== []) {
            $highest = collect($educations)
                ->map(fn ($e) => QualificationType::from($e['qualification_type']))
                ->sortByDesc(fn (QualificationType $t) => $t->rank())
                ->first()?->value;
        }

        return new StructuredResume(
            fullName: self::str($data['full_name'] ?? null, 190),
            email: self::str($data['email'] ?? null, 190),
            phone: self::str($data['phone'] ?? null, 30),
            summary: self::str($data['summary'] ?? null, 1000),
            yearsExperience: $data['years_experience'] ?? null,
            highestEducationLevel: $highest,
            skills: array_map(fn ($id, $name) => ['skill_id' => $id, 'name' => $name], array_keys($skills), array_values($skills)),
            workHistories: $work,
            educations: $educations,
            certifications: $certifications,
        );
    }

    private function bounded(string $text): string
    {
        $max = (int) config('resume.anthropic.max_chars', 60000);

        if (mb_strlen($text) > $max) {
            Log::warning('LlmStructurer: resume text exceeds cap; tail dropped', ['chars' => mb_strlen($text), 'cap' => $max]);

            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    private static function str(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** "2021", "2021-03" or "2021-03-15" → Y-m-d (first of period); anything else → null. */
    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $value = trim($value);

        if (preg_match('/^\d{4}$/', $value)) {
            $value .= '-01-01';
        } elseif (preg_match('/^\d{4}-\d{2}$/', $value)) {
            $value .= '-01';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)?->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
