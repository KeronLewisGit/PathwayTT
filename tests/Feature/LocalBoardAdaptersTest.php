<?php

use App\DTOs\JobDto;
use App\Models\JobListing;
use App\Models\JobSyncRun;
use App\Services\JobSources\CaribbeanJobsSource;
use App\Services\JobSources\EmployTtSource;
use App\Services\JobSources\JobsTtSource;
use App\Services\JobSources\Support\RobotsTxt;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function boardHtml(string $name): string
{
    return file_get_contents(base_path("tests/Fixtures/localboards/{$name}.html"));
}

/** @return list<JobDto> */
function crawlAll(object $source): array
{
    return iterator_to_array($source->fetch(), false);
}

beforeEach(function () {
    Cache::flush();
    config([
        'jobsources.remote.boards.caribbeanjobs.enabled' => true,
        'jobsources.remote.boards.jobstt.enabled' => true,
        'jobsources.remote.boards.employtt.enabled' => true,
        'jobsources.remote.boards.caribbeanjobs.delay_ms' => 0,
        'jobsources.remote.boards.jobstt.delay_ms' => 0,
        'jobsources.remote.boards.employtt.delay_ms' => 0,
    ]);
});

test('robots.txt is honoured with longest-match allow and disallow rules', function () {
    Http::fake([
        'allowed.example/robots.txt' => Http::response("User-agent: *\nDisallow: /private/\nAllow: /private/open\nDisallow: /WebService/AjaxWS.asmx/\n"),
        'blocked.example/robots.txt' => Http::response("User-agent: *\nDisallow: /\n\nUser-agent: PathwayTT\nAllow: /jobs\nDisallow: /\n"),
        'missing.example/robots.txt' => Http::response('', 404),
    ]);
    $robots = app(RobotsTxt::class);

    expect($robots->allows('https://allowed.example/jobs?page=2'))->toBeTrue()
        ->and($robots->allows('https://allowed.example/private/secret'))->toBeFalse()
        ->and($robots->allows('https://allowed.example/private/open/1'))->toBeTrue()
        ->and($robots->allows('https://allowed.example/WebService/AjaxWS.asmx/Search'))->toBeFalse()
        ->and($robots->allows('https://blocked.example/jobs/list'))->toBeTrue()   // our own token's group wins
        ->and($robots->allows('https://blocked.example/anything'))->toBeFalse()
        ->and($robots->allows('https://missing.example/whatever'))->toBeTrue();
});

test('caribbeanjobs cards become local T&T listings with excerpts only, and pagination follows the index', function () {
    $this->seed(SkillSeeder::class);
    Http::fake([
        'www.caribbeanjobs.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n"),
        'www.caribbeanjobs.com/ShowResults.aspx?Location=124&Page=2' => Http::response('<html><body></body></html>'),
        'www.caribbeanjobs.com/ShowResults.aspx?Location=124' => Http::response(boardHtml('caribbeanjobs-list')),
    ]);

    $source = app(CaribbeanJobsSource::class);
    $dtos = crawlAll($source);

    expect($dtos)->toHaveCount(3);
    $first = $dtos[0];
    expect($first->source)->toBe('caribbeanjobs')
        ->and($first->sourceJobId)->toBe('237908')
        ->and($first->title)->toBe('Customer Service Agent')
        ->and($first->country)->toBe('TT')
        ->and($first->workArrangement)->toBe('on_premises')
        ->and($first->geoEligibility)->toBe('worldwide')
        ->and($first->locationText)->toContain('Port-of-Spain')
        ->and($first->applyUrl)->toBe('https://www.caribbeanjobs.com/Customer-Service-Agent-Job-237908.aspx')
        ->and($first->postedAt)->toStartWith('2026-09-02')
        ->and($first->description)->toContain('excerpt shown here')
        ->and($first->requiredSkills)->toContain('Customer Service');

    // Index page 1 + robots only — page 2 was fetched (empty) and stopped there.
    Http::assertSentCount(3);
});

test('jobstt cards and detail pages map to listings', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    Http::fake([
        'www.jobstt.com/robots.txt' => Http::response("User-agent: *\nDisallow:\n"),
        'www.jobstt.com/job?page=2' => Http::response('<html><body></body></html>'),
        'www.jobstt.com/job/*' => Http::response(boardHtml('jobstt-detail')),
        'www.jobstt.com/job' => Http::response(boardHtml('jobstt-list')),
    ]);

    $dtos = crawlAll(app(JobsTtSource::class));

    expect($dtos)->toHaveCount(3);
    $driver = $dtos[0];
    expect($driver->source)->toBe('jobstt')
        ->and($driver->sourceJobId)->toBe('heavy-t-delivery-driver-2')
        ->and($driver->title)->toBe('Heavy T Delivery Driver')
        ->and($driver->companyName)->toBe('Seafood Enterprises')
        ->and($driver->locationText)->toBe('Cunupia or Caroni, Trinidad & Tobago')
        ->and($driver->employmentType)->toBe('permanent')
        ->and($driver->industrySlug)->toBe('logistics-shipping')
        ->and($driver->postedAt)->toStartWith('2026-09-01')
        ->and($driver->description)->toContain('Cook (Contract)') // fixture detail page
        ->and($driver->rawPayload['salary'])->toBe('Undisclosed');
});

test('employtt cards map to government-portal listings with deadlines', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    Http::fake([
        'employtt.gov.tt/robots.txt' => Http::response("User-agent: *\nDisallow:\n"),
        'employtt.gov.tt/jobs/view/*' => Http::response(boardHtml('employtt-detail')),
        'employtt.gov.tt/jobs/list' => Http::response(boardHtml('employtt-list')),
    ]);

    $dtos = crawlAll(app(EmployTtSource::class));

    expect($dtos)->toHaveCount(3);
    $first = $dtos[0];
    expect($first->source)->toBe('employtt')
        ->and($first->sourceJobId)->toBe('2627')
        ->and($first->title)->toBe('Communications Officer')
        ->and($first->companyName)->toBe('Ministry Of Rural Development And Local Government')
        ->and($first->locationText)->toBe('Maraval, Trinidad & Tobago')
        ->and($first->employmentType)->toBe('permanent')
        ->and($first->postedAt)->toStartWith('2026-09-02')
        ->and($first->closesAt)->toStartWith('2026-09-18')
        ->and($first->industrySlug)->toBe('creative-media')
        ->and($first->description)->not->toBeNull();

    expect($dtos[2]->title)->toBe('Driver/Messenger'); // entity decoded
});

test('pages disallowed by robots.txt are skipped and reported', function () {
    Http::fake([
        'www.caribbeanjobs.com/robots.txt' => Http::response("User-agent: *\nDisallow: /ShowResults.aspx\n"),
        'www.caribbeanjobs.com/*' => Http::response(boardHtml('caribbeanjobs-list')),
    ]);

    $source = app(CaribbeanJobsSource::class);
    expect(crawlAll($source))->toBe([])
        ->and($source->notes())->toContain('disallowed by robots.txt');

    Http::assertSentCount(1); // robots.txt only
});

test('local boards run daily through job:sync and are off by default where terms require permission', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    config([
        'jobsources.remote.boards.jobstt.enabled' => false,
        'jobsources.remote.boards.employtt.enabled' => false,
    ]);
    Http::fake([
        'www.caribbeanjobs.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n"),
        'www.caribbeanjobs.com/ShowResults.aspx?Location=124&Page=2' => Http::response('<html></html>'),
        'www.caribbeanjobs.com/ShowResults.aspx?Location=124' => Http::response(boardHtml('caribbeanjobs-list')),
    ]);

    $this->artisan('job:sync', ['--source' => 'caribbeanjobs'])->assertSuccessful();

    expect(JobListing::query()->where('source', 'caribbeanjobs')->count())->toBe(3)
        ->and(JobListing::query()->where('source', 'caribbeanjobs')->first()->country)->toBe('TT');

    // Within the 24h window the board is not fetched again.
    $this->artisan('job:sync', ['--source' => 'caribbeanjobs'])->assertSuccessful();
    expect(JobSyncRun::query()->where('source', 'caribbeanjobs')->latest('id')->value('notes'))->toContain('Skipped');
    expect(config('jobsources.remote.boards.caribbeanjobs.min_interval_minutes'))->toBe(1440);

    // Disabled sources never run.
    $this->artisan('job:sync', ['--source' => 'jobstt'])->assertSuccessful();
    expect(JobSyncRun::query()->where('source', 'jobstt')->exists())->toBeFalse();
});
