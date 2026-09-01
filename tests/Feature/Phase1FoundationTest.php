<?php

use App\Models\Industry;
use App\Models\LearningResource;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\LearningResourceSeeder;
use Database\Seeders\SkillSeeder;

test('reference data seeds are complete and idempotent', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class, LearningResourceSeeder::class]);
    // Re-run to prove upserts are idempotent.
    $this->seed([IndustrySeeder::class, SkillSeeder::class, LearningResourceSeeder::class]);

    expect(Industry::count())->toBe(15)
        ->and(Skill::count())->toBeGreaterThanOrEqual(200)
        ->and(LearningResource::count())->toBeGreaterThan(20);

    // Alias-aware taxonomy: JS resolves to JavaScript's match terms.
    $js = Skill::where('slug', 'javascript')->firstOrFail();
    expect($js->matchTerms())->toContain('js');

    // Both advisory tracks are represented.
    expect(LearningResource::where('provider_type', 'local_tt')->count())->toBeGreaterThan(5)
        ->and(LearningResource::where('provider_type', 'international_online')->count())->toBeGreaterThan(5);
});

test('registration requires email verification before dashboard access', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    // Unverified users are pushed to the verification notice.
    $this->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));
});

test('non-admin users cannot access the Filament admin panel', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('admins can access the Filament admin panel', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('money fields are integer cents with explicit currency', function () {
    $listing = App\Models\JobListing::factory()->create([
        'salary_min_cents' => 900000,
        'salary_currency' => 'TTD',
    ]);

    expect($listing->salary_min_cents)->toBeInt()->toBe(900000)
        ->and($listing->salary_currency)->toBe('TTD');
});
