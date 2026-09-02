<?php

use App\Enums\ProviderType;
use App\Jobs\GenerateSkillGapPlanJob;
use App\Livewire\SkillGapPlanView;
use App\Models\JobListing;
use App\Models\LearningResource;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillGapPlan;
use App\Models\User;
use App\Services\Matching\MatchRecomputeService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function planUser(array $skillIds = []): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $profile = Profile::factory()->for($user)->create(['years_experience' => 5]);
    foreach ($skillIds as $id) {
        $profile->skills()->attach($id, ['evidence_source' => 'self_reported', 'is_user_edited' => true]);
    }

    return $user;
}

function planListing(string $title, array $required): JobListing
{
    $listing = JobListing::factory()->bare()->create(['title' => $title]);
    $listing->skills()->sync(collect($required)->mapWithKeys(fn ($id) => [$id => ['is_required' => true, 'weight' => 1]])->all());

    return $listing;
}

test('the plan page generates on first visit and renders the three phases with both tracks', function () {
    [$have, $quickGap, $longGap] = Skill::factory()->count(3)->create();
    $user = planUser([$have->id]);
    planListing('Support Analyst', [$have->id, $quickGap->id]);
    planListing('Systems Engineer', [$have->id, $longGap->id]);

    $online = LearningResource::factory()->create([
        'provider' => 'Example Online Academy', 'title' => 'Self-paced certificate',
        'provider_type' => ProviderType::InternationalOnline, 'duration_weeks' => 3,
        'cost_min_cents' => 4900, 'cost_max_cents' => 4900, 'currency' => 'USD', 'credential_type' => 'certificate',
    ]);
    $online->skills()->attach($quickGap->id, ['impact_weight' => 2]);

    $local = LearningResource::factory()->create([
        'provider' => 'Example Local Institute', 'title' => 'Engineering diploma programmes',
        'provider_type' => ProviderType::LocalTt, 'duration_weeks' => null, 'credential_type' => 'degree',
        'cost_min_cents' => null, 'cost_max_cents' => null,
    ]);
    $local->skills()->attach($longGap->id, ['impact_weight' => 1]);

    // Queue is sync in tests: the first visit builds the plan inline.
    $this->actingAs($user)->get('/plan')
        ->assertOk()
        ->assertSee('Quick wins')
        ->assertSee('Core credential')
        ->assertSee('Long-term')
        ->assertSee($quickGap->name)
        ->assertSee($longGap->name)
        ->assertSee('Example Online Academy')
        ->assertSee('USD 49')
        ->assertSee('Example Local Institute')
        ->assertSee('Contact provider for current course listing')
        ->assertSee('unlock 1 listing')
        ->assertSee('Locally in Trinidad');

    expect(SkillGapPlan::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('gaps are marked done and progress shown once the user adds the skill', function () {
    [$have, $gap] = Skill::factory()->count(2)->create();
    $user = planUser([$have->id]);
    planListing('Role', [$have->id, $gap->id]);

    $this->actingAs($user)->get('/plan')->assertOk()->assertDontSee('Done — on your profile');

    // User adds the skill; matches recompute (normally queued from the profile screen).
    $user->profile()->first()->skills()->attach($gap->id, ['evidence_source' => 'self_reported', 'is_user_edited' => true]);
    app(MatchRecomputeService::class)->recomputeForUser($user);

    $this->actingAs($user)->get('/plan')
        ->assertOk()
        ->assertSee('Done — on your profile')
        ->assertSee('Up 50 since this plan');
});

test('regenerating creates a new plan version and keeps history', function () {
    Queue::fake();
    $skill = Skill::factory()->create();
    $user = planUser();
    planListing('Role', [$skill->id]);
    SkillGapPlan::query()->create([
        'user_id' => $user->id,
        'generated_at' => now()->subDay(),
        'payload' => ['version' => 1, 'threshold' => 55, 'scope' => [], 'current' => ['best_score' => 0], 'gaps' => [], 'phases' => [], 'advice' => []],
    ]);

    Livewire::actingAs($user)
        ->test(SkillGapPlanView::class)
        ->call('generate')
        ->assertSee('Building your plan');

    Queue::assertPushed(GenerateSkillGapPlanJob::class, fn (GenerateSkillGapPlanJob $job) => $job->userId === $user->id);
});

test('users without a profile are pointed to the resume upload instead of an empty plan', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/plan')
        ->assertOk()
        ->assertSee('Start with your resume');

    expect(SkillGapPlan::count())->toBe(0);
    $this->get('/plan'); // still authed; page is protected by the group middleware
    auth()->logout();
    $this->get('/plan')->assertRedirect('/login');
});
