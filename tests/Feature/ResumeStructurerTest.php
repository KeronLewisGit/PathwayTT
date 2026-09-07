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

test('bullet points and fragments never become jobs, whatever blank lines surround them', function () {
    // Shaped like a real upload: a "Title, Employer Month D, YYYY – Present" line, then
    // achievements the PDF extractor separated with blank lines and split at commas.
    $text = implode("\n", [
        'Darrion Example',
        'darrion@example.com',
        '',
        'Professional Summary',
        'Driven software developer with hands-on experience.',
        '',
        'Experience',
        '',
        'Junior Programmer, Tucker Energy Services Ltd. July 1, 2021 – Present',
        '',
        '• Performed maintenance on several high priority systems, such as the',
        'billing platform.',
        '',
        'Planned multiple internal events',
        'Generated KPAs for all employees',
        '',
        'Accomplishments',
        'Back-to-back 1st place in the internal hackathon',
        '',
        'DCIT help desk volunteer (1 year',
        '6-8 hours a week assisting students',
        'Tutored students',
        'Provided assignment assistance.',
        '',
        'IT Support Intern',
        'Republic Financial Holdings Ltd',
        'Jun 2020 - Aug 2020',
        'Resolved tickets for 300 staff.',
        '',
        'Education',
        'BSc in Computer Science',
        'University of the West Indies',
        '2021',
    ]);

    $structured = app(RuleBasedStructurer::class)->structure($text);

    expect($structured->workHistories)->toHaveCount(2);
    [$programmer, $intern] = $structured->workHistories;

    expect($programmer['title'])->toBe('Junior Programmer')
        ->and($programmer['employer'])->toBe('Tucker Energy Services Ltd')
        ->and($programmer['started_at'])->toBe('2021-01-01')
        ->and($programmer['is_current'])->toBeTrue()
        ->and($programmer['description'])->toContain('Performed maintenance')
        ->and($programmer['description'])->toContain('Tutored students')
        ->and($intern['title'])->toBe('IT Support Intern')
        ->and($intern['employer'])->toBe('Republic Financial Holdings Ltd')
        ->and($intern['started_at'])->toBe('2020-01-01')
        ->and($intern['ended_at'])->toBe('2020-12-31')
        ->and($intern['description'])->toBe('Resolved tickets for 300 staff.');

    $titles = array_column($structured->workHistories, 'title');
    expect($titles)->not->toContain('Accomplishments', 'Tutored students', 'Planned multiple internal events', 'DCIT help desk volunteer (1 year');
});

test('employer-first and numeric-date headers are read too', function () {
    $text = implode("\n", [
        'Work History',
        'Massy Stores Ltd',
        'Cashier',
        '03/2018 – 11/2019',
        '- Handled cash and card payments.',
        '',
        'Sales Representative | Digicel',
        '2020 to date',
    ]);

    $entries = app(RuleBasedStructurer::class)->parseExperience(implode("\n", array_slice(explode("\n", $text), 1)));

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['title'])->toBe('Cashier')
        ->and($entries[0]['employer'])->toBe('Massy Stores Ltd')
        ->and($entries[0]['started_at'])->toBe('2018-01-01')
        ->and($entries[0]['ended_at'])->toBe('2019-12-31')
        ->and($entries[0]['description'])->toBe('Handled cash and card payments.')
        ->and($entries[1]['title'])->toBe('Sales Representative')
        ->and($entries[1]['employer'])->toBe('Digicel')
        ->and($entries[1]['is_current'])->toBeTrue();
});
