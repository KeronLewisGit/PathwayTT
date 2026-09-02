<?php

use App\Enums\ProviderType;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\LearningResource;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SkillGapPlan;
use App\Models\User;
use App\Services\Advisory\SkillGapAnalyzerInterface;
use App\Services\Advisory\SkillGapPlanner;
use Database\Seeders\LearningResourceSeeder;
use Database\Seeders\SkillSeeder;

function gapUser(array $skillIds = [], array $profile = []): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $p = Profile::factory()->for($user)->create($profile + ['years_experience' => 5, 'summary' => null]);
    foreach ($skillIds as $id) {
        $p->skills()->attach($id, ['evidence_source' => 'self_reported', 'is_user_edited' => true]);
    }

    return $user;
}

function gapListing(string $title, array $required, array $attributes = []): JobListing
{
    $listing = JobListing::factory()->bare()->create(['title' => $title] + $attributes);
    $listing->skills()->sync(collect($required)->mapWithKeys(fn ($id) => [$id => ['is_required' => true, 'weight' => 1]])->all());

    return $listing;
}

function gapItem(array $payload, int $skillId): ?array
{
    return collect($payload['gaps'])->firstWhere('skill.id', $skillId);
}

test('gaps are ranked by impact per unit of effort with projections computed from real listings', function () {
    [$a, $b, $c] = Skill::factory()->count(3)->create();
    $user = gapUser([$a->id]);

    gapListing('Needs A and B (1)', [$a->id, $b->id]); // 50 now → 100 with B: unlocks
    gapListing('Needs A and B (2)', [$a->id, $b->id]); // same
    gapListing('Needs A and C', [$a->id, $c->id]);     // 50 now → 100 with C: unlocks

    $quick = LearningResource::factory()->create(['provider_type' => ProviderType::InternationalOnline, 'duration_weeks' => 2, 'credential_type' => 'certificate']);
    $quick->skills()->attach($b->id, ['impact_weight' => 2]);
    $degree = LearningResource::factory()->create(['provider_type' => ProviderType::LocalTt, 'duration_weeks' => null, 'credential_type' => 'degree', 'cost_min_cents' => null]);
    $degree->skills()->attach($c->id, ['impact_weight' => 1]);

    $payload = app(SkillGapAnalyzerInterface::class)->analyze($user);

    expect($payload['current'])->toMatchArray(['eligible_count' => 3, 'best_score' => 50, 'above_threshold' => 0])
        ->and($payload['gaps'])->toHaveCount(2);

    $gapB = gapItem($payload, $b->id);
    expect($gapB['rank'])->toBe(1)
        ->and($gapB['jobs_requiring'])->toBe(2)
        ->and($gapB['jobs_unlocked'])->toBe(2)
        ->and($gapB['avg_lift'])->toBe(50)
        ->and($gapB['best_after'])->toBe(100)
        ->and($gapB['effort_weeks'])->toBe(2)
        ->and($gapB['effort_estimated'])->toBeFalse()
        ->and($gapB['phase'])->toBe('quick')
        ->and($gapB['resources']['online'])->toHaveCount(1)
        ->and($gapB['resources']['local'])->toBe([]);

    $gapC = gapItem($payload, $c->id);
    expect($gapC['rank'])->toBe(2)
        ->and($gapC['jobs_unlocked'])->toBe(1)
        ->and($gapC['effort_weeks'])->toBe(104) // degree with no stated duration → config estimate
        ->and($gapC['effort_estimated'])->toBeTrue()
        ->and($gapC['phase'])->toBe('long')
        ->and($gapC['resources']['local'][0]['cost'])->toBeNull()
        ->and($gapC['impact_per_week'])->toBeLessThan($gapB['impact_per_week']);

    $phases = collect($payload['phases'])->keyBy('key');
    expect($phases['quick']['items'])->toHaveCount(1)
        ->and($phases['core']['items'])->toHaveCount(0)
        ->and($phases['long']['items'])->toHaveCount(1);
});

test('the scope follows preferences and widens when they leave too few listings', function () {
    $skill = Skill::factory()->create();
    $target = Industry::factory()->create(['name' => 'Target Industry']);
    $other = Industry::factory()->create(['name' => 'Other Industry']);
    $user = gapUser();
    $user->jobPreference()->create(['industry_id' => $target->id, 'work_arrangements' => ['on_premises']]);

    gapListing('Target 1', [$skill->id], ['industry_id' => $target->id]);
    for ($i = 1; $i <= 6; $i++) {
        gapListing("Other {$i}", [$skill->id], ['industry_id' => $other->id]);
    }

    $widened = app(SkillGapAnalyzerInterface::class)->analyze($user);
    expect($widened['scope']['widened'])->toBeTrue()
        ->and($widened['scope']['industry'])->toBe('Target Industry')
        ->and($widened['scope']['listings_considered'])->toBe(7);

    for ($i = 2; $i <= 5; $i++) {
        gapListing("Target {$i}", [$skill->id], ['industry_id' => $target->id]);
    }

    $focused = app(SkillGapAnalyzerInterface::class)->analyze($user);
    expect($focused['scope']['widened'])->toBeFalse()
        ->and($focused['scope']['listings_considered'])->toBe(5)
        ->and(gapItem($focused, $skill->id)['jobs_requiring'])->toBe(5);

    // Remote-only preference excludes the on-premises listings entirely → widened again.
    $user->jobPreference()->update(['industry_id' => null, 'work_arrangements' => ['remote_international']]);
    expect(app(SkillGapAnalyzerInterface::class)->analyze($user)['scope']['widened'])->toBeTrue();
});

test('non-credential advice appears only when the listings support it', function () {
    $skill = Skill::factory()->create();
    $user = gapUser([$skill->id], ['has_nis' => false]);

    // Local listing only: no remote advice.
    gapListing('Local Clerk', [$skill->id], ['required_credentials' => ['nis']]);
    $payload = app(SkillGapAnalyzerInterface::class)->analyze($user);
    $keys = collect($payload['advice'])->pluck('key');
    expect($keys)->not->toContain('remote_portfolio')
        ->and($keys)->toContain('credential_nis')
        ->and($keys)->toContain('set_preferences')
        ->and(collect($payload['advice'])->firstWhere('key', 'credential_nis')['jobs'])->toBe(1);

    // Add an eligible remote listing: the three remote tips appear.
    gapListing('Remote Support', [$skill->id], [
        'work_arrangement' => WorkArrangement::RemoteInternational, 'country' => 'US', 'required_overlap_hours' => 4,
    ]);
    $keys = collect(app(SkillGapAnalyzerInterface::class)->analyze($user)['advice'])->pluck('key');
    expect($keys)->toContain('remote_portfolio', 'usd_payments', 'overlap_statement');

    // A profile that already shows remote signals doesn't get told again.
    $user->profile()->update(['summary' => 'Two years fully remote for a US client; portfolio on GitHub.']);
    $keys = collect(app(SkillGapAnalyzerInterface::class)->analyze($user)['advice'])->pluck('key');
    expect($keys)->not->toContain('remote_portfolio');

    // Ticking the credential removes that tip too.
    $user->profile()->update(['has_nis' => true]);
    $keys = collect(app(SkillGapAnalyzerInterface::class)->analyze($user)['advice'])->pluck('key');
    expect($keys)->not->toContain('credential_nis');
});

test('missing profile basics are surfaced as advice with counts', function () {
    $skill = Skill::factory()->create();
    $user = gapUser([$skill->id], ['years_experience' => null, 'highest_education_level' => null]);
    gapListing('Senior Role', [$skill->id], ['min_years_experience' => 5, 'min_education_level' => 'bsc']);
    gapListing('Junior Role', [$skill->id]);

    $advice = collect(app(SkillGapAnalyzerInterface::class)->analyze($user)['advice'])->keyBy('key');
    expect($advice['add_education']['jobs'])->toBe(1)
        ->and($advice['add_experience']['jobs'])->toBe(1);
});

test('plans persist as versions with the industry snapshot', function () {
    $skill = Skill::factory()->create();
    $industry = Industry::factory()->create();
    $user = gapUser();
    $user->jobPreference()->create(['industry_id' => $industry->id]);
    gapListing('Role', [$skill->id], ['industry_id' => $industry->id]);

    $plan = app(SkillGapPlanner::class)->generate($user);
    expect($plan->target_industry_id)->toBe($industry->id)
        ->and($plan->payload['version'])->toBe(1)
        ->and($plan->payload['gaps'][0]['skill']['id'])->toBe($skill->id);

    app(SkillGapPlanner::class)->generate($user);
    expect(SkillGapPlan::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('the seeded catalog maps every provider to taxonomy skills without inventing courses', function () {
    $this->seed([SkillSeeder::class, LearningResourceSeeder::class]);

    $fcc = LearningResource::query()->where('slug', 'freecodecamp')->firstOrFail();
    expect($fcc->skills()->pluck('name')->all())->toContain('JavaScript', 'HTML', 'Python')
        ->and($fcc->skills()->where('name', 'JavaScript')->first()->pivot->impact_weight)->toBe(2)
        ->and($fcc->duration_weeks)->toBeNull()
        ->and($fcc->cost_min_cents)->toBeNull();

    $mic = LearningResource::query()->where('slug', 'mic-institute-of-technology-mic-it')->firstOrFail();
    expect($mic->skills()->pluck('name')->all())->toContain('Welding', 'Pipefitting');

    $unmapped = LearningResource::query()->doesntHave('skills')->pluck('provider');
    expect($unmapped->all())->toBe([]);

    // Idempotent
    $this->seed(LearningResourceSeeder::class);
    expect($fcc->skills()->count())->toBe($fcc->fresh()->skills()->count());
});
