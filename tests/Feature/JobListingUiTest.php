<?php

use App\Enums\GeoEligibility;
use App\Enums\WorkArrangement;
use App\Livewire\JobList;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\Skill;
use App\Models\User;
use App\Services\SalaryFormatter;
use App\Services\SettingsService;
use Livewire\Livewire;

function verifiedJobSeeker(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('the job list shows only active, open listings', function () {
    $open = JobListing::factory()->create(['title' => 'Open Role Alpha']);
    $inactive = JobListing::factory()->create(['title' => 'Inactive Role Beta', 'is_active' => false]);
    $closed = JobListing::factory()->closed()->create(['title' => 'Closed Role Gamma']);

    $this->actingAs(verifiedJobSeeker())
        ->get('/jobs')
        ->assertOk()
        ->assertSee($open->title)
        ->assertDontSee($inactive->title)
        ->assertDontSee($closed->title);
});

test('us-only remote jobs are hidden by default and flagged ineligible when shown', function () {
    $worldwide = JobListing::factory()->remoteWorldwide()->create(['title' => 'Worldwide Support Rep']);
    $usOnly = JobListing::factory()->usOnly()->create(['title' => 'US Only Engineer']);

    expect($usOnly->ineligibilityReason())->toContain('Restricted to applicants in US')
        ->and($worldwide->ineligibilityReason())->toBeNull();

    // Default: eligible-only filter on.
    Livewire::actingAs(verifiedJobSeeker())
        ->test(JobList::class)
        ->assertSee('Worldwide Support Rep')
        ->assertDontSee('US Only Engineer')
        ->set('eligibleOnly', false)
        ->assertSee('US Only Engineer')
        ->assertSee('Not eligible from T&amp;T', false);

    // Detail page states the reason and never hides it.
    $this->actingAs(verifiedJobSeeker())
        ->get(route('jobs.show', $usOnly))
        ->assertOk()
        ->assertSee('Not eligible from Trinidad')
        ->assertSee('Restricted to applicants in US');
});

test('the job list filters by search, industry, arrangement and employment type', function () {
    $ict = Industry::factory()->create(['name' => 'ICT & Software', 'slug' => 'ict-software']);
    $energy = Industry::factory()->create(['name' => 'Energy', 'slug' => 'energy']);

    JobListing::factory()->create([
        'title' => 'Laravel Developer', 'company_name' => 'Acme', 'industry_id' => $ict->id,
        'work_arrangement' => WorkArrangement::OnPremises, 'employment_type' => 'permanent',
    ]);
    JobListing::factory()->create([
        'title' => 'Rig Technician', 'company_name' => 'Offshore Ltd', 'industry_id' => $energy->id,
        'work_arrangement' => WorkArrangement::OnPremises, 'employment_type' => 'contract',
    ]);
    JobListing::factory()->remoteWorldwide()->create([
        'title' => 'Remote React Developer', 'company_name' => 'Globex', 'industry_id' => $ict->id,
        'employment_type' => 'contract',
    ]);

    $component = Livewire::actingAs(verifiedJobSeeker())->test(JobList::class);

    $component->set('search', 'developer')
        ->assertSee('Laravel Developer')->assertSee('Remote React Developer')->assertDontSee('Rig Technician');

    $component->set('search', '')->set('industry', (string) $energy->id)
        ->assertSee('Rig Technician')->assertDontSee('Laravel Developer');

    $component->set('industry', '')->set('arrangement', 'remote_international')
        ->assertSee('Remote React Developer')->assertDontSee('Laravel Developer')->assertDontSee('Rig Technician');

    $component->set('arrangement', '')->set('employment', 'permanent')
        ->assertSee('Laravel Developer')->assertDontSee('Rig Technician')->assertDontSee('Remote React Developer');

    $component->call('clearFilters')
        ->assertSee('Laravel Developer')->assertSee('Rig Technician')->assertSee('Remote React Developer');
});

test('the location dropdown filters by country or remote and lists only countries present', function () {
    JobListing::factory()->bare()->create(['title' => 'Local Clerk', 'country' => 'TT']);
    JobListing::factory()->bare()->create(['title' => 'Tobago Front Desk', 'country' => 'TT', 'location_text' => 'Crown Point, Tobago']);
    JobListing::factory()->remoteWorldwide()->create(['title' => 'Remote From Canada Co', 'country' => 'CA']);
    JobListing::factory()->remoteWorldwide()->create(['title' => 'Remote From UK Co', 'country' => 'GB']);
    JobListing::factory()->bare()->create(['title' => 'Closed Elsewhere', 'country' => 'DE', 'is_active' => false]);

    $component = Livewire::actingAs(verifiedJobSeeker())->test(JobList::class);

    $locations = $component->instance()->locations;
    expect(array_keys($locations))->toBe(['TT', 'remote', 'CA', 'GB']) // T&T, remote, then by name; inactive DE excluded
        ->and($locations['CA'])->toBe('Canada');

    $component->set('location', 'TT')
        ->assertSee('Local Clerk')->assertSee('Tobago Front Desk')->assertDontSee('Remote From Canada Co');

    $component->set('location', 'remote')
        ->assertSee('Remote From Canada Co')->assertSee('Remote From UK Co')->assertDontSee('Local Clerk');

    $component->set('location', 'GB')
        ->assertSee('Remote From UK Co')->assertDontSee('Remote From Canada Co')->assertDontSee('Local Clerk');

    $component->call('clearFilters')->assertSet('location', '')->assertSee('Local Clerk')->assertSee('Remote From Canada Co');
});

test('the feed hides demo listings by default and shows freshness with source counts', function () {
    Illuminate\Support\Facades\Queue::fake();
    JobListing::factory()->bare()->create(['title' => 'Real Local Role', 'source' => 'csv']);
    JobListing::factory()->remoteWorldwide()->create(['title' => 'Real Remote Role', 'source' => 'remotive']);
    JobListing::factory()->bare()->create(['title' => '[DEMO] Pretend Role', 'source' => 'demo']);
    App\Models\JobSyncRun::query()->create(['source' => 'remotive', 'started_at' => now()->subMinutes(5), 'finished_at' => now()->subMinutes(4), 'fetched_count' => 1]);

    $this->actingAs(verifiedJobSeeker())->get('/jobs')
        ->assertOk()
        ->assertSee('Live feed')
        ->assertSee('2 open listings')
        ->assertSee('Remotive · 1')
        ->assertSee('Real Remote Role')
        ->assertDontSee('[DEMO] Pretend Role');

    // Fresh feed: no sync queued. Stale feed: one queued (unique job).
    Illuminate\Support\Facades\Queue::assertNotPushed(App\Jobs\SyncJobSourcesJob::class);
    App\Models\JobSyncRun::query()->update(['finished_at' => now()->subHours(3)]);
    $this->actingAs(verifiedJobSeeker())->get('/jobs')->assertOk()->assertSee('3 hours ago');
    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SyncJobSourcesJob::class);

    // Demo listings can be switched back into the feed for local demos.
    config(['jobsources.show_demo_listings' => true]);
    $this->actingAs(verifiedJobSeeker())->get('/jobs')->assertOk()->assertSee('[DEMO] Pretend Role')->assertSee('3 open listings');
});

test('saved preferences pre-fill the job list filters', function () {
    $user = verifiedJobSeeker();
    $ict = Industry::factory()->create();
    $user->jobPreference()->create(['industry_id' => $ict->id, 'work_arrangements' => ['remote_international']]);

    Livewire::actingAs($user)
        ->test(JobList::class)
        ->assertSet('industry', (string) $ict->id)
        ->assertSet('arrangement', 'remote_international');
});

test('the job detail page links out to the original posting and lists skills', function () {
    $job = JobListing::factory()->create([
        'title' => 'Accounts Clerk',
        'apply_url' => 'https://employer.example/careers/accounts-clerk',
        'requirements' => ['2+ CSEC passes including Maths'],
    ]);
    $required = Skill::factory()->create(['name' => 'Bookkeeping', 'slug' => 'bookkeeping']);
    $preferred = Skill::factory()->create(['name' => 'QuickBooks', 'slug' => 'quickbooks']);
    $job->skills()->attach([
        $required->id => ['is_required' => true, 'weight' => 1],
        $preferred->id => ['is_required' => false, 'weight' => 1],
    ]);

    $this->get(route('jobs.show', $job))->assertRedirect('/login');

    $this->actingAs(verifiedJobSeeker())
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Accounts Clerk')
        ->assertSee('https://employer.example/careers/accounts-clerk')
        ->assertSee('Apply on original posting')
        ->assertSee('2+ CSEC passes including Maths')
        ->assertSeeInOrder(['Required', 'Bookkeeping', 'Nice to have', 'QuickBooks']);
});

test('salaries render in both currencies using the admin-set fx rate', function () {
    app(SettingsService::class)->set('fx.usd_to_ttd', ['rate' => 6.80]);

    $usd = JobListing::factory()->make([
        'salary_min_cents' => 120000, 'salary_max_cents' => 180000,
        'salary_currency' => 'USD', 'salary_period' => 'monthly',
    ]);
    $ttd = JobListing::factory()->make([
        'salary_min_cents' => 900000, 'salary_max_cents' => null,
        'salary_currency' => 'TTD', 'salary_period' => 'monthly',
    ]);
    $none = JobListing::factory()->make(['salary_min_cents' => null, 'salary_max_cents' => null]);

    $formatter = app(SalaryFormatter::class);

    expect($formatter->format($usd))->toBe('USD 1,200–1,800 / month (≈ TTD 8,160–12,240)')
        ->and($formatter->format($ttd))->toBe('TTD 9,000+ / month (≈ USD 1,324+)')
        ->and($formatter->format($none))->toBeNull();

    // No FX rate configured → primary currency only, never a made-up conversion.
    app(SettingsService::class)->set('fx.usd_to_ttd', null);
    config(['fx.usd_to_ttd' => null]);
    expect($formatter->format($usd))->toBe('USD 1,200–1,800 / month');
});
