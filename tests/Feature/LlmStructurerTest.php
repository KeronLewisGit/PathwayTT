<?php

use Anthropic\Client;
use App\Services\Resume\LlmStructurer;
use App\Services\Resume\ResumeStructurerInterface;
use App\Services\Resume\RuleBasedStructurer;
use App\Services\Skills\SkillMatcher;
use Database\Seeders\SkillSeeder;

const LLM_RESUME_TEXT = <<<'TXT'
Keisha Ramnarine
keisha@example.com | 868-555-0199

Professional Summary
Accounts clerk with three years of experience.

Skills
Microsoft Excel, QuickBooks, Bookkeeping

Experience
Accounts Clerk at Island Finance, 2022 - present
TXT;

/** A structurer whose network call is replaced by a canned response. */
function llmStructurerReturning(?string $json, ?Throwable $throws = null): LlmStructurer
{
    return new class($json, $throws) extends LlmStructurer
    {
        public function __construct(private ?string $json, private ?Throwable $throws)
        {
            parent::__construct(
                new Client(apiKey: 'test-key'),
                app(SkillMatcher::class),
                app(RuleBasedStructurer::class),
            );
        }

        protected function complete(string $text): ?string
        {
            if ($this->throws) {
                throw $this->throws;
            }

            return $this->json;
        }
    };
}

function llmPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Keisha Ramnarine',
        'email' => 'keisha@example.com',
        'phone' => '868-555-0199',
        'summary' => 'Accounts clerk with three years of experience.',
        'years_experience' => 3,
        'highest_education_level' => 'cape',
        'skills' => ['MS Excel', 'QuickBooks', 'Bookkeeping', 'Not A Real Skill'],
        'work_histories' => [[
            'employer' => 'Island Finance', 'title' => 'Accounts Clerk',
            'started_at' => '2022-03', 'ended_at' => null, 'is_current' => true,
            'description' => 'Reconciled ledgers and processed payables.',
        ]],
        'educations' => [[
            'institution' => 'St. Augustine Girls High School', 'qualification_type' => 'cape',
            'field' => 'Accounting', 'completed_at' => '2019',
        ]],
        'certifications' => [[
            'name' => 'QuickBooks Certified User', 'issuer' => 'Intuit', 'issued_at' => '2023-05-10',
        ]],
    ], $overrides);
}

test('a valid model response is validated, normalised and skills resolved to the taxonomy', function () {
    $this->seed(SkillSeeder::class);

    $resume = llmStructurerReturning(json_encode(llmPayload()))->structure(LLM_RESUME_TEXT);

    expect($resume->fullName)->toBe('Keisha Ramnarine')
        ->and($resume->email)->toBe('keisha@example.com')
        ->and($resume->yearsExperience)->toBe(3)
        ->and($resume->highestEducationLevel)->toBe('cape');

    $skillNames = collect($resume->skills)->pluck('name')->all();
    expect($skillNames)->toContain('Microsoft Excel', 'QuickBooks', 'Bookkeeping') // "MS Excel" resolved via alias
        ->and($skillNames)->not->toContain('Not A Real Skill');

    expect($resume->workHistories)->toHaveCount(1)
        ->and($resume->workHistories[0]['started_at'])->toBe('2022-03-01')
        ->and($resume->workHistories[0]['ended_at'])->toBeNull()
        ->and($resume->workHistories[0]['is_current'])->toBeTrue()
        ->and($resume->educations[0]['completed_at'])->toBe('2019-01-01')
        ->and($resume->certifications[0]['issued_at'])->toBe('2023-05-10');
});

test('highest education is derived from the education rows when the model leaves it null', function () {
    $resume = llmStructurerReturning(json_encode(llmPayload([
        'highest_education_level' => null,
        'educations' => [
            ['institution' => 'UWI', 'qualification_type' => 'bsc', 'field' => 'Accounting', 'completed_at' => '2020'],
            ['institution' => 'School', 'qualification_type' => 'csec', 'field' => null, 'completed_at' => '2014'],
        ],
    ])))->structure(LLM_RESUME_TEXT);

    expect($resume->highestEducationLevel)->toBe('bsc');
});

test('invalid or refused responses fall back to the rule-based parser instead of failing', function () {
    $this->seed(SkillSeeder::class);

    $cases = [
        'refusal' => null,
        'not json' => 'Sure! Here is the resume: ...',
        'bad enum' => json_encode(llmPayload(['highest_education_level' => 'masters-ish'])),
        'bad years' => json_encode(llmPayload(['years_experience' => 99])),
        'bad rows' => json_encode(llmPayload(['work_histories' => [['employer' => null, 'title' => 'x']]])),
    ];

    foreach ($cases as $label => $response) {
        $resume = llmStructurerReturning($response)->structure(LLM_RESUME_TEXT);
        // Rule-based output for the fixture: email found, skills matched from the text.
        expect($resume->email)->toBe('keisha@example.com', "case: {$label}");
        expect(collect($resume->skills)->pluck('name')->all())->toContain('Bookkeeping');
    }

    // Transport errors too.
    $resume = llmStructurerReturning(null, new RuntimeException('connection reset'))->structure(LLM_RESUME_TEXT);
    expect($resume->email)->toBe('keisha@example.com');
});

test('the structurer binding follows the driver and key configuration', function () {
    config(['resume.driver' => 'rule', 'resume.anthropic.api_key' => 'sk-test']);
    expect(app(ResumeStructurerInterface::class))->toBeInstanceOf(RuleBasedStructurer::class);

    config(['resume.driver' => 'llm', 'resume.anthropic.api_key' => '']);
    expect(app(ResumeStructurerInterface::class))->toBeInstanceOf(RuleBasedStructurer::class);

    config(['resume.driver' => 'llm', 'resume.anthropic.api_key' => 'sk-test']);
    expect(app(ResumeStructurerInterface::class))->toBeInstanceOf(LlmStructurer::class);
});

test('the request schema is strict and mirrors the qualification enum', function () {
    $schema = LlmStructurer::schema();

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toContain('skills', 'work_histories', 'educations', 'certifications')
        ->and($schema['properties']['educations']['items']['properties']['qualification_type']['enum'])->toContain('csec', 'cape', 'bsc', 'professional')
        ->and($schema['properties']['work_histories']['items']['additionalProperties'])->toBeFalse();
});
