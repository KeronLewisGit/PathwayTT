<?php

use App\Enums\ApplicationStatus;
use App\Livewire\ApplicationTracker;
use App\Livewire\JobTrackButton;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\User;
use Livewire\Livewire;

function trackerUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('a user can save a job and move it through the pipeline', function () {
    $user = trackerUser();
    $job = JobListing::factory()->create();

    Livewire::actingAs($user)
        ->test(JobTrackButton::class, ['jobListingId' => $job->id])
        ->assertSee('Save job')
        ->call('save')
        ->assertSee('Saved')
        ->call('save'); // idempotent

    expect(Application::count())->toBe(1);
    $application = Application::query()->firstOrFail();
    expect($application->status)->toBe(ApplicationStatus::Saved)
        ->and($application->applied_at)->toBeNull();

    // saved → offer is not a legal transition
    Livewire::actingAs($user)
        ->test(JobTrackButton::class, ['jobListingId' => $job->id])
        ->call('setStatus', 'offer')
        ->assertHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Saved);

    Livewire::actingAs($user)
        ->test(JobTrackButton::class, ['jobListingId' => $job->id])
        ->call('setStatus', 'applied')->assertHasNoErrors()
        ->call('setStatus', 'interviewing')->assertHasNoErrors()
        ->call('setStatus', 'offer')->assertHasNoErrors()
        ->assertSee('Offer')
        ->assertDontSee('Mark ');

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::Offer)
        ->and($application->applied_at)->not->toBeNull();

    Livewire::actingAs($user)
        ->test(JobTrackButton::class, ['jobListingId' => $job->id])
        ->call('remove')
        ->assertSee('Save job');
    expect(Application::count())->toBe(0);
});

test('the tracker lists applications with status filters and notes', function () {
    $user = trackerUser();
    $saved = Application::factory()->for($user)->create([
        'status' => ApplicationStatus::Saved,
        'job_listing_id' => JobListing::factory()->create(['title' => 'Warehouse Supervisor Role'])->id,
    ]);
    $applied = Application::factory()->for($user)->create([
        'status' => ApplicationStatus::Applied,
        'applied_at' => now(),
        'job_listing_id' => JobListing::factory()->create(['title' => 'Payroll Officer Role'])->id,
    ]);

    $this->actingAs($user)->get('/applications')
        ->assertOk()
        ->assertSee('Warehouse Supervisor Role')
        ->assertSee('Payroll Officer Role');

    $component = Livewire::actingAs($user)->test(ApplicationTracker::class);

    $component->set('status', 'applied')
        ->assertSee('Payroll Officer Role')
        ->assertDontSee('Warehouse Supervisor Role');

    $component->set('status', '')
        ->set("notes.{$saved->id}", 'Spoke to HR on Monday')
        ->call('saveNotes', $saved->id)
        ->assertHasNoErrors();
    expect($saved->fresh()->notes)->toBe('Spoke to HR on Monday');

    $component->call('setStatus', $saved->id, 'applied');
    expect($saved->fresh()->status)->toBe(ApplicationStatus::Applied)
        ->and($saved->fresh()->applied_at)->not->toBeNull();

    $component->call('setStatus', $applied->id, 'saved')
        ->assertHasErrors("status.{$applied->id}");

    $component->call('remove', $saved->id);
    expect(Application::count())->toBe(1);
});

test('users cannot modify each other\'s tracked jobs', function () {
    $other = Application::factory()->create(['status' => ApplicationStatus::Saved]);
    $user = trackerUser();

    try {
        Livewire::actingAs($user)
            ->test(ApplicationTracker::class)
            ->call('setStatus', $other->id, 'applied');
    } catch (Throwable) {
        // 404 from the owner-scoped lookup — either way nothing changes
    }

    expect($other->fresh()->status)->toBe(ApplicationStatus::Saved);
});

test('the applications page requires a verified login', function () {
    $this->get('/applications')->assertRedirect('/login');
    $this->actingAs(trackerUser())->get('/applications')->assertOk()->assertSee('Nothing tracked yet');
});
