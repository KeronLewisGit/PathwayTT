<?php

use App\Filament\Pages\MatchingSettings;
use App\Jobs\RecomputeAllMatchesJob;
use App\Jobs\RecomputeUserMatchesJob;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function matchingAdmin(): User
{
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}

test('admins can edit matching weights, threshold and fx rate', function () {
    Queue::fake();
    $admin = matchingAdmin();

    $this->actingAs($admin)->get('/admin/matching-settings')->assertOk()->assertSee('Score weights');

    Livewire::actingAs($admin)
        ->test(MatchingSettings::class)
        ->fillForm([
            'weights' => [
                'required_skills' => 40, 'bonus_skills' => 5, 'experience' => 15, 'education' => 10,
                'industry' => 10, 'arrangement' => 10, 'geo_timezone' => 10,
            ],
            'advisory_threshold' => 60,
            'fx_rate' => 6.75,
            'fx_note' => 'Central Bank mid-rate',
            'fx_needs_review' => false,
            'recompute' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SettingsService::class);
    expect($settings->matchingWeights()['required_skills'])->toBe(40)
        ->and($settings->advisoryThreshold())->toBe(60)
        ->and($settings->usdToTtdRate())->toBe(6.75);

    Queue::assertPushed(RecomputeAllMatchesJob::class);

    // Weights that don't sum to 100 are rejected and nothing is saved.
    Livewire::actingAs($admin)
        ->test(MatchingSettings::class)
        ->fillForm(['weights' => [
            'required_skills' => 50, 'bonus_skills' => 5, 'experience' => 15, 'education' => 10,
            'industry' => 10, 'arrangement' => 10, 'geo_timezone' => 10,
        ]])
        ->call('save');

    expect(app(SettingsService::class)->matchingWeights()['required_skills'])->toBe(40);
});

test('admins can list users and re-run matching for one of them', function () {
    Queue::fake();
    $admin = matchingAdmin();
    $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Keisha Ramnarine']);

    $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Keisha Ramnarine');

    Livewire::actingAs($admin)
        ->test(App\Filament\Resources\UserResource\Pages\ListUsers::class)
        ->callTableAction('recompute', $user);

    Queue::assertPushed(RecomputeUserMatchesJob::class, fn (RecomputeUserMatchesJob $job) => $job->userId === $user->id);
});

test('admins can create a user with the admin flag set explicitly', function () {
    $admin = matchingAdmin();

    Livewire::actingAs($admin)
        ->test(App\Filament\Resources\UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'secret-password-123',
            'is_admin' => true,
            'email_verified_at' => now()->toDateTimeString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('email', 'new-admin@example.com')->firstOrFail();
    expect($created->is_admin)->toBeTrue()
        ->and($created->email_verified_at)->not->toBeNull();
});
