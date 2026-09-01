<?php

use App\Services\Resume\RuleBasedStructurer;
use Database\Seeders\SkillSeeder;

function sampleResumeText(): string
{
    return implode("\n", [
        'John Ramlogan',
        'Port of Spain, Trinidad',
        'john.ramlogan@example.com',
        '(868) 555-1234',
        '',
        'Professional Summary',
        'Motivated developer with local and remote experience.',
        '',
        'Work Experience',
        '',
        'Web Developer at TechTT Ltd',
        '2019 - Present',
        'Built Laravel applications with MySQL and JavaScript.',
        '',
        'Junior Developer — Island Software',
        '2016 - 2019',
        'Maintained PHP systems and MS Excel reports.',
        '',
        'Education',
        '',
        'BSc in Computer Science',
        'University of the West Indies, St. Augustine',
        '2016',
        '',
        'Skills',
        'PHP, Laravel, MySQL, JavaScript, MS Excel, Git',
        '',
        'Certifications',
        'AWS Certified Cloud Practitioner — Amazon Web Services, 2022',
    ]);
}

test('sections are detected from headings', function () {
    $sections = app(RuleBasedStructurer::class)->splitSections(sampleResumeText());

    expect($sections)->toHaveKeys(['preamble', 'summary', 'experience', 'education', 'skills', 'certifications']);
});

test('skills match through canonical names and aliases', function () {
    $this->seed(SkillSeeder::class);

    $skills = collect(app(RuleBasedStructurer::class)->matchSkills(sampleResumeText()))->pluck('name');

    // Direct names…
    expect($skills)->toContain('PHP')->toContain('Laravel')->toContain('MySQL')->toContain('JavaScript')
        // …and alias hits: "MS Excel" → Microsoft Excel, "AWS" → Amazon Web Services.
        ->toContain('Microsoft Excel')->toContain('Amazon Web Services');
});

test('word boundaries prevent false positives', function () {
    $this->seed(SkillSeeder::class);

    // "Rusty" must not match a skill "R"; "Scarpentry" must not match "Carpentry".
    $skills = collect(app(RuleBasedStructurer::class)->matchSkills('I enjoy Scarpentry and Rusty things.'))
        ->pluck('name');

    expect($skills)->not->toContain('Carpentry');
});

test('experience blocks yield employers, titles and date ranges', function () {
    $structured = app(RuleBasedStructurer::class)->structure(sampleResumeText());

    expect($structured->workHistories)->toHaveCount(2);

    [$first, $second] = $structured->workHistories;

    expect($first['title'])->toBe('Web Developer')
        ->and($first['employer'])->toBe('TechTT Ltd')
        ->and($first['is_current'])->toBeTrue()
        ->and($first['started_at'])->toBe('2019-01-01')
        ->and($second['title'])->toBe('Junior Developer')
        ->and($second['employer'])->toBe('Island Software')
        ->and($second['is_current'])->toBeFalse()
        ->and($second['ended_at'])->toBe('2019-12-31');
});

test('education maps to the T&T qualification ladder', function () {
    $structured = app(RuleBasedStructurer::class)->structure(sampleResumeText());

    expect($structured->educations)->not->toBeEmpty()
        ->and($structured->educations[0]['qualification_type'])->toBe('bsc')
        ->and($structured->educations[0]['institution'])->toContain('University of the West Indies')
        ->and($structured->highestEducationLevel)->toBe('bsc');
});

test('contact details and name are extracted', function () {
    $structured = app(RuleBasedStructurer::class)->structure(sampleResumeText());

    expect($structured->email)->toBe('john.ramlogan@example.com')
        ->and($structured->phone)->toContain('868')
        ->and($structured->fullName)->toBe('John Ramlogan');
});

test('certifications parse name, issuer and year', function () {
    $structured = app(RuleBasedStructurer::class)->structure(sampleResumeText());

    expect($structured->certifications)->toHaveCount(1)
        ->and($structured->certifications[0]['name'])->toBe('AWS Certified Cloud Practitioner')
        ->and($structured->certifications[0]['issuer'])->toBe('Amazon Web Services')
        ->and($structured->certifications[0]['issued_at'])->toBe('2022-12-31');
});
