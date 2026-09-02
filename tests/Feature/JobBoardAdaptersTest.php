<?php

use App\DTOs\JobDto;
use App\Jobs\RecomputeAllMatchesJob;
use App\Models\JobListing;
use App\Models\JobSyncRun;
use App\Services\JobSources\ArbeitnowSource;
use App\Services\JobSources\HimalayasSource;
use App\Services\JobSources\JobicySource;
use App\Services\JobSources\LocalBoardSource;
use App\Services\JobSources\RemoteOkSource;
use App\Services\JobSources\RemotiveSource;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function boardFixture(string $board): array
{
    return json_decode(file_get_contents(base_path("tests/Fixtures/jobsources/{$board}.json")), true);
}

/** @return list<JobDto> */
function fetchAll(object $source): array
{
    return iterator_to_array($source->fetch(), false);
}

beforeEach(function () {
    config([
        'jobsources.remote.boards.remotive.enabled' => true,
        'jobsources.remote.boards.jobicy.enabled' => true,
        'jobsources.remote.boards.himalayas.enabled' => true,
        'jobsources.remote.boards.remoteok.enabled' => true,
        'jobsources.remote.boards.arbeitnow.enabled' => true,
        'jobsources.remote.import_ineligible' => false,
    ]);
});

test('remotive listings map to normalized dtos and country-restricted ones are skipped', function () {
    $this->seed(SkillSeeder::class);
    $fixture = boardFixture('remotive');
    $fixture['jobs'][1]['candidate_required_location'] = 'USA';
    $fixture['jobs'][2]['candidate_required_location'] = 'LATAM, Europe';
    Http::fake(['remotive.com/*' => Http::response($fixture)]);

    $source = app(RemotiveSource::class);
    $dtos = fetchAll($source);

    expect($dtos)->toHaveCount(2)
        ->and($source->notes())->toContain('1 listings skipped');

    $first = $dtos[0];
    expect($first->source)->toBe('remotive')
        ->and($first->sourceJobId)->toBe((string) $fixture['jobs'][0]['id'])
        ->and($first->workArrangement)->toBe('remote_international')
        ->and($first->geoEligibility)->toBe('worldwide')
        ->and($first->isOpenToCaribbean)->toBeTrue()
        ->and($first->applyUrl)->toBe($fixture['jobs'][0]['url'])
        ->and($first->postedAt)->not->toBeNull()
        ->and($first->description)->not->toContain('<')
        ->and($first->rawPayload['candidate_required_location'])->toBe('Worldwide');

    // Title "Senior React Full-stack Developer" → React is a required skill; the rest nice-to-have.
    $react = collect($dtos)->first(fn (JobDto $d) => str_contains($d->title, 'React'));
    if ($react) {
        expect($react->requiredSkills)->toContain('React')
            ->and($react->seniority)->toBe('senior');
    }

    $latam = $dtos[1];
    expect($latam->geoEligibility)->toBe('region_restricted')->and($latam->isOpenToCaribbean)->toBeTrue();

    // Importing ineligible listings is a config switch.
    config(['jobsources.remote.import_ineligible' => true]);
    expect(fetchAll(app(RemotiveSource::class)))->toHaveCount(3);

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0], 'PathwayTT'));
});

test('jobicy listings carry salary in cents, employment type and seniority', function () {
    $fixture = boardFixture('jobicy');
    $fixture['jobs'][0]['jobGeo'] = 'Anywhere';
    Http::fake(['jobicy.com/*' => Http::response($fixture)]);

    $source = app(JobicySource::class);
    $dtos = fetchAll($source);

    expect($dtos)->toHaveCount(1) // the two USA-only ones are skipped
        ->and($source->notes())->toContain('2 listings skipped');

    $dto = $dtos[0];
    expect($dto->source)->toBe('jobicy')
        ->and($dto->geoEligibility)->toBe('worldwide')
        ->and($dto->salaryMinCents)->toBe(25000000)
        ->and($dto->salaryMaxCents)->toBe(29000000)
        ->and($dto->salaryCurrency)->toBe('USD')
        ->and($dto->salaryPeriod)->toBe('yearly')
        ->and($dto->employmentType)->toBe('permanent')
        ->and($dto->seniority)->toBe('manager')
        ->and($dto->industrySlug)->toBe('healthcare')
        ->and($dto->applyUrl)->toStartWith('https://jobicy.com/jobs/');
});

test('himalayas honours location and timezone restrictions and paginates by cursor', function () {
    $page1 = boardFixture('himalayas');
    $page1['nextCursor'] = 'abc';
    $page1['jobs'][1]['timezoneRestrictions'] = [-10, -9, -8, -7]; // no UTC-4 → closed
    $page2 = boardFixture('himalayas');
    $page2['jobs'] = [];
    $page2['nextCursor'] = null;

    Http::fake([
        'himalayas.app/jobs/api?*cursor=abc*' => Http::response($page2),
        'himalayas.app/jobs/api*' => Http::response($page1),
    ]);

    $source = app(HimalayasSource::class);
    $dtos = fetchAll($source);

    expect($dtos)->toHaveCount(1)
        ->and($source->notes())->toContain('2 listings skipped');

    $dto = $dtos[0];
    expect($dto->source)->toBe('himalayas')
        ->and($dto->geoEligibility)->toBe('worldwide')
        ->and($dto->isOpenToCaribbean)->toBeTrue()
        ->and($dto->employmentType)->toBe('contract')
        ->and($dto->salaryMinCents)->toBe(5000)
        ->and($dto->salaryMaxCents)->toBe(10000)
        ->and($dto->salaryPeriod)->toBe('hourly')
        ->and($dto->salaryCurrency)->toBe('USD')
        ->and($dto->seniority)->toBe('mid')
        ->and($dto->closesAt)->not->toBeNull()
        ->and($dto->applyUrl)->toStartWith('https://himalayas.app/');

    Http::assertSentCount(2);
});

test('remote ok skips its legal notice, keeps unknown locations as not stated and follows its terms', function () {
    $fixture = boardFixture('remoteok');
    $fixture[3]['location'] = 'Worldwide';
    $fixture[3]['salary_min'] = 60000;
    $fixture[3]['salary_max'] = 80000;
    Http::fake(['remoteok.com/*' => Http::response($fixture)]);

    $dtos = fetchAll(app(RemoteOkSource::class));

    expect($dtos)->toHaveCount(3);

    $goa = $dtos[0];
    expect($goa->source)->toBe('remoteok')
        ->and($goa->geoEligibility)->toBeNull()
        ->and($goa->isOpenToCaribbean)->toBeNull()
        ->and($goa->salaryMinCents)->toBeNull()
        ->and($goa->companyName)->toBe('St. Regis Hotels & Resorts'); // entities decoded

    $worldwide = $dtos[2];
    expect($worldwide->geoEligibility)->toBe('worldwide')
        ->and($worldwide->salaryMinCents)->toBe(6000000)
        ->and($worldwide->salaryCurrency)->toBe('USD')
        ->and($worldwide->applyUrl)->toContain('remoteOK.com');
});

test('arbeitnow imports only remote listings and marks their region as not stated', function () {
    $fixture = boardFixture('arbeitnow');
    $fixture['data'][] = ['slug' => 'onsite-1', 'title' => 'On-site role', 'remote' => false, 'url' => 'https://www.arbeitnow.com/x', 'tags' => [], 'job_types' => [], 'location' => 'Berlin', 'created_at' => 1788354032, 'company_name' => 'X', 'description' => ''];
    Http::fake(['arbeitnow.com/*' => Http::response($fixture)]);

    $dtos = fetchAll(app(ArbeitnowSource::class));

    expect($dtos)->toHaveCount(2)
        ->and($dtos[0]->geoEligibility)->toBe('region_restricted')
        ->and($dtos[0]->isOpenToCaribbean)->toBeNull()
        ->and($dtos[0]->locationText)->toContain('Germany')
        ->and($dtos[0]->postedAt)->not->toBeNull();

    Http::assertSentCount(1); // links.next is null in the fixture → no second page
});

test('a board is not called again inside its rate-limit window', function () {
    Http::fake(['remotive.com/*' => Http::response(boardFixture('remotive'))]);
    JobSyncRun::query()->create([
        'source' => 'remotive', 'started_at' => now()->subMinutes(20), 'finished_at' => now()->subMinutes(19),
        'fetched_count' => 10, 'created_count' => 10,
    ]);

    $source = app(RemotiveSource::class);
    expect(fetchAll($source))->toBe([])
        ->and($source->notes())->toContain('Skipped');
    Http::assertNothingSent();

    // A failed run does not count as a successful fetch.
    JobSyncRun::query()->update(['error' => 'HTTP 500']);
    expect(fetchAll(app(RemotiveSource::class)))->not->toBe([]);
    Http::assertSentCount(1);
});

test('board errors are recorded per source without aborting the sync', function () {
    Queue::fake();
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    config(['jobsources.remote.boards.remoteok.enabled' => false, 'jobsources.remote.boards.arbeitnow.enabled' => false]);

    $himalayas = boardFixture('himalayas');
    $himalayas['nextCursor'] = null;
    Http::fake([
        'remotive.com/*' => Http::response('Service unavailable', 503),
        'jobicy.com/*' => Http::response(boardFixture('jobicy')),
        'himalayas.app/*' => Http::response($himalayas),
    ]);

    $this->artisan('job:sync')->assertFailed();

    $runs = JobSyncRun::query()->get()->keyBy('source');
    expect($runs['remotive']->error)->toContain('HTTP 503')
        ->and($runs['jobicy']->error)->toBeNull()
        ->and($runs['jobicy']->notes)->toContain('skipped')
        ->and($runs['himalayas']->created_count)->toBe(2)
        ->and($runs->has('local_boards'))->toBeFalse(); // stub is disabled

    $listing = JobListing::query()->where('source', 'himalayas')->firstOrFail();
    expect($listing->work_arrangement->value)->toBe('remote_international')
        ->and($listing->geo_eligibility->value)->toBe('worldwide')
        ->and($listing->apply_url)->toStartWith('https://himalayas.app/')
        ->and($listing->raw_payload['timezoneRestrictions'])->not->toBeEmpty();

    Queue::assertPushed(RecomputeAllMatchesJob::class);

    // Re-running upserts rather than duplicating.
    Http::fake(['himalayas.app/*' => Http::response($himalayas), 'jobicy.com/*' => Http::response(boardFixture('jobicy')), 'remotive.com/*' => Http::response(boardFixture('remotive'))]);
    JobSyncRun::query()->delete();
    $this->artisan('job:sync', ['--source' => 'himalayas'])->assertSuccessful();
    expect(JobListing::query()->where('source', 'himalayas')->count())->toBe(2);
});

test('the local board source is a documented stub that never runs', function () {
    $stub = new LocalBoardSource;

    expect($stub->enabled())->toBeFalse()
        ->and(iterator_to_array($stub->fetch()))->toBe([])
        ->and($stub->notes())->toContain('no public API');
});
