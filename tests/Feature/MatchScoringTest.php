<?php

use App\Enums\GeoEligibility;
use App\Enums\QualificationType;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\Skill;
use App\Services\Matching\CandidateProfile;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorerInterface;
use App\Services\SettingsService;

function scoringCandidate(array $overrides = []): CandidateProfile
{
    return new CandidateProfile(...array_merge([
        'userId' => 1,
        'hasProfile' => true,
        'skillIds' => [],
        'yearsExperience' => null,
        'education' => null,
        'preferredIndustryId' => null,
        'workArrangements' => [],
        'employmentTypes' => [],
        'seniority' => null,
        'credentials' => ['nis' => false, 'bir' => false, 'drivers_permit' => false, 'police_certificate' => false],
    ], $overrides));
}

/** A bare listing (no stated requirements) with N required + M preferred skills attached. */
function scoringListing(int $required, int $preferred = 0, array $attributes = []): JobListing
{
    $listing = JobListing::factory()->bare()->create($attributes);

    $sync = [];
    foreach (Skill::factory()->count($required)->create() as $skill) {
        $sync[$skill->id] = ['is_required' => true, 'weight' => 1];
    }
    foreach (Skill::factory()->count($preferred)->create() as $skill) {
        $sync[$skill->id] = ['is_required' => false, 'weight' => 1];
    }
    $listing->skills()->sync($sync);

    return $listing->load('skills');
}

function requiredSkillIds(JobListing $listing): array
{
    return $listing->skills->filter(fn ($s) => (bool) $s->pivot->is_required)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
}

function preferredSkillIds(JobListing $listing): array
{
    return $listing->skills->reject(fn ($s) => (bool) $s->pivot->is_required)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
}

function scoreComponent(MatchResult $result, string $key): array
{
    return collect($result->breakdown['components'])->firstWhere('key', $key);
}

test('hard filters produce ineligibility, not a low score', function () {
    $scorer = app(MatchScorerInterface::class);
    $candidate = scoringCandidate();

    expect($scorer->score($candidate, JobListing::factory()->closed()->create())->ineligibilityReason)
        ->toBe('This listing is closed');

    expect($scorer->score($candidate, JobListing::factory()->usOnly()->create())->ineligibilityReason)
        ->toContain('Restricted to applicants in US');

    $permit = JobListing::factory()->remoteWorldwide()->create(['country' => 'US', 'requires_work_permit' => true]);
    expect($scorer->score($candidate, $permit)->ineligibilityReason)->toContain('work permit');

    // A local employer asking for the right to work in T&T is fine for residents.
    $local = JobListing::factory()->bare()->create(['requires_work_permit' => true]);
    $result = $scorer->score($candidate, $local);
    expect($result->eligible)->toBeTrue()->and($result->ineligibilityReason)->toBeNull();
});

test('a complete fit scores 100 with a full, explainable breakdown', function () {
    $industry = Industry::factory()->create();
    $job = scoringListing(2, 1, [
        'industry_id' => $industry->id,
        'min_years_experience' => 3,
        'min_education_level' => QualificationType::Diploma,
    ]);

    $candidate = scoringCandidate([
        'skillIds' => [...requiredSkillIds($job), ...preferredSkillIds($job)],
        'yearsExperience' => 5,
        'education' => QualificationType::Bachelors,
        'preferredIndustryId' => $industry->id,
        'workArrangements' => ['on_premises'],
    ]);

    $result = app(MatchScorerInterface::class)->score($candidate, $job);

    expect($result->eligible)->toBeTrue()
        ->and($result->score)->toBe(100)
        ->and($result->missingSkills)->toBe([])
        ->and($result->breakdown['cap'])->toBeNull()
        ->and($result->breakdown['components'])->toHaveCount(7)
        ->and(scoreComponent($result, 'geo_timezone')['applicable'])->toBeFalse() // local role
        ->and($result->breakdown['summary'])->toContain('You have 2 of 2 required skills');

    $points = collect($result->breakdown['components'])->where('applicable', true)->sum('points');
    expect($points)->toEqualWithDelta(100, 0.5);
});

test('missing required skills cap the score so a 90% match is impossible without them', function () {
    $industry = Industry::factory()->create();
    $job = scoringListing(3, 0, [
        'industry_id' => $industry->id,
        'min_years_experience' => 1,
        'min_education_level' => QualificationType::Csec,
    ]);
    $ids = requiredSkillIds($job);

    // Everything else is perfect, so only the skills coverage pulls the raw score down.
    $strong = [
        'yearsExperience' => 5,
        'education' => QualificationType::Bachelors,
        'preferredIndustryId' => $industry->id,
        'workArrangements' => ['on_premises'],
    ];
    $scorer = app(MatchScorerInterface::class);

    $missingOne = $scorer->score(scoringCandidate($strong + ['skillIds' => [$ids[0], $ids[1]]]), $job);
    expect($missingOne->breakdown['raw_score'])->toBeGreaterThan(80)
        ->and($missingOne->score)->toBe(80)
        ->and($missingOne->breakdown['cap'])->toBe(80)
        ->and($missingOne->breakdown['cap_reason'])->toContain('Missing 1 required skill')
        ->and(collect($missingOne->missingSkills)->where('required', true))->toHaveCount(1);

    $missingTwo = $scorer->score(scoringCandidate($strong + ['skillIds' => [$ids[0]]]), $job);
    expect($missingTwo->breakdown['raw_score'])->toBeGreaterThan(60)
        ->and($missingTwo->score)->toBe(60)
        ->and($missingTwo->breakdown['cap'])->toBe(60);
});

test('factors a listing does not state are skipped and their weight redistributed', function () {
    $job = scoringListing(2); // bare: no seniority, years, education; candidate has no prefs
    $scorer = app(MatchScorerInterface::class);

    $all = $scorer->score(scoringCandidate(['skillIds' => requiredSkillIds($job)]), $job);
    expect($all->score)->toBe(100)
        ->and(collect($all->breakdown['components'])->where('applicable', true))->toHaveCount(1)
        ->and(scoreComponent($all, 'experience')['applicable'])->toBeFalse()
        ->and(scoreComponent($all, 'industry')['applicable'])->toBeFalse();

    $half = $scorer->score(scoringCandidate(['skillIds' => [requiredSkillIds($job)[0]]]), $job);
    expect($half->breakdown['raw_score'])->toBe(50)
        ->and($half->score)->toBe(50)
        ->and($half->breakdown['cap'])->toBe(80);
});

test('experience and education score proportionally and surface as gaps', function () {
    $job = scoringListing(1, 0, [
        'min_years_experience' => 4,
        'min_education_level' => QualificationType::Bachelors,
    ]);

    $result = app(MatchScorerInterface::class)->score(scoringCandidate([
        'skillIds' => requiredSkillIds($job),
        'yearsExperience' => 2,
        'education' => QualificationType::Csec,
    ]), $job);

    expect(scoreComponent($result, 'experience')['score'])->toBe(50)
        ->and(scoreComponent($result, 'education')['score'])->toBe(25)
        // (35*100 + 15*50 + 10*25) / 60
        ->and($result->score)->toBe(75)
        ->and($result->breakdown['gaps'])->toContain('About 4 years of experience expected (you list 2)')
        ->and($result->breakdown['gaps'])->toContain("Bachelor's degree expected (your highest is CSEC / CXC)");
});

test('seniority estimates the years required when a listing does not state them', function () {
    $job = scoringListing(1, 0, ['seniority' => 'senior']);
    expect($job->requiredYears())->toBe(5);

    $result = app(MatchScorerInterface::class)->score(scoringCandidate([
        'skillIds' => requiredSkillIds($job),
        'yearsExperience' => 2,
    ]), $job);

    expect(scoreComponent($result, 'experience')['score'])->toBe(40);

    // Entry level = no minimum: not applicable even if the user hasn't entered years.
    $entry = scoringListing(1, 0, ['seniority' => 'entry']);
    $entryResult = app(MatchScorerInterface::class)->score(scoringCandidate(['skillIds' => requiredSkillIds($entry)]), $entry);
    expect(scoreComponent($entryResult, 'experience')['applicable'])->toBeFalse()
        ->and($entryResult->score)->toBe(100);
});

test('remote roles score timezone overlap feasibility from AST', function () {
    $job = scoringListing(1, 0, [
        'work_arrangement' => WorkArrangement::RemoteInternational,
        'geo_eligibility' => GeoEligibility::Worldwide,
        'country' => 'US',
        'required_overlap_hours' => 10,
    ]);
    $candidate = scoringCandidate(['skillIds' => requiredSkillIds($job)]);
    $scorer = app(MatchScorerInterface::class);

    expect(scoreComponent($scorer->score($candidate, $job), 'geo_timezone')['score'])->toBe(40);

    $job->update(['required_overlap_hours' => 4]);
    expect(scoreComponent($scorer->score($candidate, $job), 'geo_timezone')['score'])->toBe(100);

    $job->update(['required_overlap_hours' => null]);
    expect(scoreComponent($scorer->score($candidate, $job), 'geo_timezone')['score'])->toBe(100);

    // Region-restricted but silent on the Caribbean: eligible, flagged as unclear.
    $job->update(['geo_eligibility' => GeoEligibility::RegionRestricted, 'is_open_to_caribbean' => null]);
    $unclear = $scorer->score($candidate, $job);
    expect($unclear->eligible)->toBeTrue()
        ->and(scoreComponent($unclear, 'geo_timezone')['score'])->toBe(70)
        ->and(scoreComponent($unclear, 'geo_timezone')['detail'])->toContain('not stated');
});

test('admin weight overrides change scores without code changes', function () {
    $job = scoringListing(2, 0, ['min_years_experience' => 1]);
    $candidate = scoringCandidate(['skillIds' => [requiredSkillIds($job)[0]], 'yearsExperience' => 5]);

    // Defaults: (35*50 + 15*100) / 50 = 65
    expect(app(MatchScorerInterface::class)->score($candidate, $job)->score)->toBe(65);

    app(SettingsService::class)->set('matching.weights', ['required_skills' => 15, 'experience' => 35]);

    // Override: (15*50 + 35*100) / 50 = 80 (and the 1-missing cap is also 80)
    expect(app(MatchScorerInterface::class)->score($candidate, $job)->score)->toBe(80);
});

test('missing local credentials are advice, not a hard filter', function () {
    $job = scoringListing(1, 0, ['required_credentials' => ['nis', 'drivers_permit']]);

    $result = app(MatchScorerInterface::class)->score(scoringCandidate([
        'skillIds' => requiredSkillIds($job),
        'credentials' => ['nis' => true, 'bir' => false, 'drivers_permit' => false, 'police_certificate' => false],
    ]), $job);

    expect($result->eligible)->toBeTrue()
        ->and($result->score)->toBe(100)
        ->and($result->breakdown['gaps'])->toContain("Requires a Driver's permit — mark it on your profile if you already have one")
        ->and($result->breakdown['gaps'])->not->toContain('Requires a NIS number — mark it on your profile if you already have one');
});
