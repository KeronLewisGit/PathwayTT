<?php

use App\Enums\ApplicationStatus;
use App\Livewire\JobTrackButton;
use App\Models\Achievement;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\Profile;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use App\Services\Engagement\AchievementService;
use App\Services\Engagement\ProfileStrength;
use App\Services\Matching\MatchRecomputeService;
use Livewire\Livewire;

function engagedUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('profile strength adds up from concrete, linked next actions', function () {
    $user = engagedUser();
    $strength = app(ProfileStrength::class)->for($user);

    expect($strength['score'])->toBe(0)
        ->and($strength['next'])->toHaveCount(3)
        ->and($strength['next'][0]['label'])->toBe('Upload your resume') // highest payoff first (ties keep order)
        ->and(array_sum(config('engagement.strength')))->toBe(100);

    Resume::factory()->for($user)->create();
    $profile = Profile::factory()->for($user)->create(['full_name' => 'A', 'phone' => '868', 'region' => 'Arima', 'summary' => 'Hi', 'has_nis' => true]);
    $profile->skills()->attach(Skill::factory()->count(8)->create()->pluck('id')->all(), ['evidence_source' => 'self_reported']);
    $profile->workHistories()->create(['employer' => 'X', 'title' => 'Y']);
    $profile->educations()->create(['institution' => 'UWI', 'qualification_type' => 'bsc']);
    $user->jobPreference()->create(['work_arrangements' => ['on_premises']]);

    $full = app(ProfileStrength::class)->for($user);
    expect($full['score'])->toBe(100)->and($full['next'])->toBe([]);
});

test('milestones are awarded once, from real progress, with points and levels', function () {
    $user = engagedUser();
    $service = app(AchievementService::class);

    expect($service->evaluate($user))->toBeEmpty();

    Resume::factory()->for($user)->create();
    $new = $service->evaluate($user);
    expect($new->pluck('key')->all())->toBe(['resume_uploaded'])
        ->and($service->evaluate($user))->toBeEmpty() // idempotent
        ->and(Achievement::query()->where('user_id', $user->id)->count())->toBe(1);

    // Applications drive the search milestones.
    $job = JobListing::factory()->create();
    $application = Application::factory()->for($user)->create(['job_listing_id' => $job->id, 'status' => ApplicationStatus::Saved]);
    $application->transitionTo(ApplicationStatus::Applied);
    $application->transitionTo(ApplicationStatus::Interviewing);
    $application->transitionTo(ApplicationStatus::Offer);

    $keys = $service->evaluate($user)->pluck('key');
    expect($keys)->toContain('first_saved', 'first_applied', 'first_interview', 'first_offer')
        ->and($keys)->not->toContain('applied_5');

    $board = $service->board($user);
    expect($board['points'])->toBe(10 + 5 + 20 + 40 + 100)
        ->and($board['level']['name'])->toBe('In the running')
        ->and($board['level']['next'])->toBe('Front runner')
        ->and(collect($board['items'])->firstWhere('key', 'first_offer')['new'])->toBeTrue();

    $service->markSeen($user);
    expect(collect($service->board($user)['items'])->firstWhere('key', 'first_offer')['new'])->toBeFalse();
});

test('the match milestone is awarded when recomputation crosses the threshold', function () {
    $user = engagedUser();
    $skill = Skill::factory()->create();
    Profile::factory()->for($user)->create()->skills()->attach($skill->id, ['evidence_source' => 'self_reported']);
    $listing = JobListing::factory()->bare()->create();
    $listing->skills()->attach($skill->id, ['is_required' => true, 'weight' => 1]);

    app(MatchRecomputeService::class)->recomputeForUser($user);

    expect(app(AchievementService::class)->evaluate($user)->pluck('key'))->toContain('first_match');
});

test('saving a job from a listing announces milestones as toasts', function () {
    $user = engagedUser();
    $job = JobListing::factory()->create();

    Livewire::actingAs($user)
        ->test(JobTrackButton::class, ['jobListingId' => $job->id])
        ->call('save')
        ->assertDispatched('notify', message: 'Saved to your tracker.', tone: 'success')
        ->assertDispatched('notify', tone: 'celebrate');

    expect(Achievement::query()->where('user_id', $user->id)->pluck('key'))->toContain('first_saved');
});

test('the weekly goal counts applications marked applied this week', function () {
    $user = engagedUser();
    $service = app(AchievementService::class);

    expect($service->weeklyGoal($user))->toMatchArray(['goal' => 3, 'count' => 0, 'met' => false]);

    Application::factory()->for($user)->count(2)->create(['status' => ApplicationStatus::Applied, 'applied_at' => now()]);
    Application::factory()->for($user)->create(['status' => ApplicationStatus::Applied, 'applied_at' => now()->subWeeks(2)]);

    expect($service->weeklyGoal($user))->toMatchArray(['count' => 2, 'met' => false, 'progress' => 67]);
});

test('the dashboard shows strength, goal, momentum and milestones', function () {
    $user = engagedUser();
    Resume::factory()->for($user)->create();
    app(AchievementService::class)->evaluate($user);

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertSee('Profile strength')
        ->assertSee("This week's goal", false)
        ->assertSee('Momentum')
        ->assertSee('Getting started')
        ->assertSee('Milestones')
        ->assertSee('Resume in')
        ->assertSee('New');

    // Viewing the dashboard marks milestones as seen.
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('badge-warning">New');
});

test('the tracker shows the pipeline and weekly goal', function () {
    $user = engagedUser();
    Application::factory()->for($user)->create(['status' => ApplicationStatus::Interviewing, 'applied_at' => now()]);

    $this->actingAs($user)->get('/applications')
        ->assertOk()
        ->assertSee('Your pipeline')
        ->assertSeeInOrder(["This week's goal", '/ 3 applied'], false);
});
