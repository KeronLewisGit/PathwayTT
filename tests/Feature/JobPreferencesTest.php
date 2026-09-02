<?php

use App\Livewire\JobPreferences;
use App\Models\Industry;
use App\Models\User;
use Livewire\Livewire;

test('the preferences page requires a verified login', function () {
    $this->get('/preferences')->assertRedirect('/login');

    $unverified = User::factory()->create(['email_verified_at' => null]);
    $this->actingAs($unverified)->get('/preferences')->assertRedirect(route('verification.notice', absolute: false));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($user)->get('/preferences')->assertOk()->assertSeeLivewire(JobPreferences::class);
});

test('users can save job preferences with money stored as integer cents', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $industry = Industry::factory()->create();

    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->set('industry_id', $industry->id)
        ->set('work_arrangements', ['remote_international', 'hybrid_local'])
        ->set('employment_types', ['contract'])
        ->set('seniority', 'mid')
        ->set('min_salary', '8,000.50')
        ->set('min_salary_currency', 'TTD')
        ->set('min_salary_period', 'monthly')
        ->set('willing_to_relocate', true)
        ->set('availability_date', '2026-10-01')
        ->call('save')
        ->assertHasNoErrors();

    $pref = $user->fresh()->jobPreference;
    expect($pref->industry_id)->toBe($industry->id)
        ->and($pref->work_arrangements)->toBe(['remote_international', 'hybrid_local'])
        ->and($pref->employment_types)->toBe(['contract'])
        ->and($pref->seniority)->toBe('mid')
        ->and($pref->min_salary_cents)->toBeInt()->toBe(800050)
        ->and($pref->min_salary_currency)->toBe('TTD')
        ->and($pref->min_salary_period)->toBe('monthly');

    $profile = $user->fresh()->profile;
    expect($profile->willing_to_relocate)->toBeTrue()
        ->and($profile->availability_date->format('Y-m-d'))->toBe('2026-10-01');

    // Re-mounting hydrates the saved values (dollars shown, not cents).
    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->assertSet('industry_id', $industry->id)
        ->assertSet('min_salary', '8,000.50')
        ->assertSet('work_arrangements', ['remote_international', 'hybrid_local']);

    // Saving again updates the single row rather than creating another.
    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->set('min_salary', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->jobPreference->min_salary_cents)->toBeNull()
        ->and(App\Models\JobPreference::count())->toBe(1);
});

test('invalid preference values are rejected', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->set('industry_id', 999999)
        ->set('work_arrangements', ['on_the_moon'])
        ->set('seniority', 'god-tier')
        ->set('min_salary', 'lots')
        ->set('min_salary_currency', 'EUR')
        ->call('save')
        ->assertHasErrors(['industry_id', 'work_arrangements.0', 'seniority', 'min_salary', 'min_salary_currency']);

    expect(App\Models\JobPreference::count())->toBe(0);
});
