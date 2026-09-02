<?php

use App\Enums\ApplicationStatus;
use App\Models\JobListing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoActivitySeeder;
use Database\Seeders\DemoJobSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\LearningResourceSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SkillSeeder;

function seedDemo(): void
{
    config(['app.demo_data' => true]);
    test()->seed([IndustrySeeder::class, SkillSeeder::class, LearningResourceSeeder::class, SettingSeeder::class]);
    test()->seed([DemoUserSeeder::class, DemoJobSeeder::class, DemoActivitySeeder::class]);
}

test('demo data never seeds outside local unless explicitly allowed', function () {
    config(['app.demo_data' => false]);
    $this->seed([IndustrySeeder::class, SkillSeeder::class, LearningResourceSeeder::class, SettingSeeder::class]);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'demo@pathwaytt.test')->exists())->toBeFalse()
        ->and(JobListing::query()->where('source', 'demo')->count())->toBe(0);

    $this->artisan('demo:reset')->assertFailed();
});

test('the demo persona is mid-search: profile, matches, plan, applications and milestones', function () {
    seedDemo();

    $user = User::query()->where('email', 'demo@pathwaytt.test')->firstOrFail();
    $profile = $user->profile()->firstOrFail();

    expect($user->name)->toBe('Aaliyah Mohammed')
        ->and($profile->skills()->count())->toBeGreaterThanOrEqual(10)
        ->and($profile->workHistories()->count())->toBe(2)
        ->and($profile->educations()->count())->toBe(2)
        ->and($profile->certifications()->count())->toBe(1)
        ->and($user->jobPreference)->not->toBeNull();

    $best = (int) $user->jobMatches()->where('is_eligible', true)->max('score');
    expect($best)->toBeGreaterThanOrEqual(55)
        ->and($user->skillGapPlans()->count())->toBe(1)
        ->and($user->applications()->count())->toBe(3)
        ->and($user->applications()->where('status', ApplicationStatus::Interviewing)->count())->toBe(1)
        ->and($user->achievements()->pluck('key'))->toContain('first_applied', 'first_interview', 'profile_full', 'first_match', 'plan_generated');

    // Second persona and empty tester accounts exist and are verified.
    expect(User::query()->where('email', 'marcus@pathwaytt.test')->firstOrFail()->profile()->firstOrFail()->skills()->count())->toBeGreaterThanOrEqual(8)
        ->and(User::query()->where('email', 'like', 'tester%@pathwaytt.test')->whereNotNull('email_verified_at')->count())->toBe(3)
        ->and(User::query()->where('email', 'tester1@pathwaytt.test')->firstOrFail()->profile()->exists())->toBeFalse();

    // Re-seeding is idempotent.
    $this->seed(DemoActivitySeeder::class);
    expect($user->applications()->count())->toBe(3)
        ->and($user->skillGapPlans()->count())->toBe(1)
        ->and($profile->workHistories()->count())->toBe(2);
});

test('demo listings are clearly labelled, local-heavy and skill-linked', function () {
    seedDemo();

    $listings = JobListing::query()->where('source', 'demo')->get();

    expect($listings->count())->toBeGreaterThanOrEqual(18)
        ->and($listings->every(fn ($l) => str_starts_with($l->title, '[DEMO]') && str_starts_with($l->apply_url, 'https://example.com/')))->toBeTrue()
        ->and($listings->where('country', 'TT')->count())->toBeGreaterThanOrEqual(14)
        ->and($listings->every(fn ($l) => $l->skills()->exists()))->toBeTrue()
        ->and($listings->filter(fn ($l) => $l->ineligibilityReason() !== null)->count())->toBe(1);
});

test('demo:reset returns the personas to their scripted state', function () {
    seedDemo();

    $user = User::query()->where('email', 'demo@pathwaytt.test')->firstOrFail();
    $user->applications()->delete();
    $user->profile()->first()->skills()->detach();
    $user->forceFill(['name' => 'Changed By Tester'])->save();

    $this->artisan('demo:reset')->assertSuccessful();

    $fresh = User::query()->where('email', 'demo@pathwaytt.test')->firstOrFail();
    expect($fresh->id)->not->toBe($user->id)
        ->and($fresh->name)->toBe('Aaliyah Mohammed')
        ->and($fresh->applications()->count())->toBe(3)
        ->and($fresh->profile()->firstOrFail()->skills()->count())->toBeGreaterThanOrEqual(10)
        ->and(JobListing::query()->where('source', 'demo')->count())->toBeGreaterThanOrEqual(18);
});
