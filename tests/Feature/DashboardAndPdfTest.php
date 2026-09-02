<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\Advisory\SkillGapPlanner;
use App\Services\Matching\MatchRecomputeService;

function dashboardUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('a new user is told to upload a resume first', function () {
    $this->actingAs(dashboardUser())->get('/dashboard')
        ->assertOk()
        ->assertSee('Upload your resume')
        ->assertSee('Not uploaded yet')
        ->assertSee('Not computed yet');
});

test('the dashboard shows live match, application and plan figures', function () {
    $user = dashboardUser();
    [$a, $b] = Skill::factory()->count(2)->create();
    $profile = Profile::factory()->for($user)->create(['years_experience' => 4]);
    $profile->skills()->attach($a->id, ['evidence_source' => 'self_reported']);
    $user->jobPreference()->create(['work_arrangements' => ['on_premises']]);

    $good = JobListing::factory()->bare()->create(['title' => 'Good Fit']);
    $good->skills()->attach($a->id, ['is_required' => true, 'weight' => 1]);
    // Requires only the skill the user lacks: 0% coverage → well under the threshold.
    $gap = JobListing::factory()->bare()->create(['title' => 'Needs B']);
    $gap->skills()->attach([$b->id => ['is_required' => true, 'weight' => 1]]);

    app(MatchRecomputeService::class)->recomputeForUser($user);
    app(SkillGapPlanner::class)->generate($user);
    Application::factory()->for($user)->create(['job_listing_id' => $good->id, 'status' => ApplicationStatus::Interviewing]);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Apply to your top matches')
        ->assertSee('1 skill on record')
        ->assertSee('1 of 2 eligible listings at or above 55')
        ->assertSee('1 tracked')
        ->assertSee('gaps closed');
});

test('the plan can be downloaded as a pdf', function () {
    $user = dashboardUser();
    [$have, $gap] = Skill::factory()->count(2)->create(['name' => fn () => 'Skill '.fake()->unique()->word()]);
    $profile = Profile::factory()->for($user)->create(['years_experience' => 4]);
    $profile->skills()->attach($have->id, ['evidence_source' => 'self_reported']);
    $listing = JobListing::factory()->bare()->create(['title' => 'Target Role']);
    $listing->skills()->attach([$have->id => ['is_required' => true, 'weight' => 1], $gap->id => ['is_required' => true, 'weight' => 1]]);

    // No plan yet → 404 with a hint.
    $this->actingAs($user)->get('/plan/pdf')->assertNotFound();

    app(SkillGapPlanner::class)->generate($user);

    $response = $this->actingAs($user)->get('/plan/pdf');
    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('PathwayTT-skills-plan-'.now()->format('Y-m-d').'.pdf');

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the pdf is private to its owner', function () {
    $this->get('/plan/pdf')->assertRedirect('/login');
});
