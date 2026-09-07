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

test('the real caribbeanjobs robots.txt allows our crawler but blocks the named AI bots', function () {
    Http::fake(['www.caribbeanjobs.com/robots.txt' => Http::response(file_get_contents(base_path('tests/Fixtures/localboards/caribbeanjobs-robots.txt')))]);
    $robots = app(RobotsTxt::class);

    expect($robots->allows('https://www.caribbeanjobs.com/ShowResults.aspx?Location=124&Page=2'))->toBeTrue()
        ->and($robots->allows('https://www.caribbeanjobs.com/WebService/AjaxWS.asmx/Search'))->toBeFalse()
        ->and($robots->allows('https://www.caribbeanjobs.com/ShowResults.aspx?Location=124', 'ClaudeBot'))->toBeFalse()
        ->and($robots->allows('https://www.caribbeanjobs.com/anything', 'GPTBot'))->toBeFalse();
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
        ->and($first->requiredSkills)->toContain('Customer Service')
        // Cards carry no category: industry and employment type come from the title/excerpt,
        // otherwise every local listing vanishes under an industry filter.
        ->and($first->industrySlug)->toBe('bpo-contact-centre')
        ->and($dtos[1]->industrySlug)->toBe('tourism-hospitality')
        ->and($dtos[2]->industrySlug)->toBe('energy-petrochemicals');

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

test('local-board titles classify into T&T industries and employment types', function () {
    $industry = new ReflectionMethod(App\Services\JobSources\HtmlBoardSource::class, 'industryFromTitle');
    $employment = new ReflectionMethod(App\Services\JobSources\HtmlBoardSource::class, 'employmentFromTitle');

    // Real titles from the live CaribbeanJobs feed.
    $cases = [
        'Senior Officer Platform Engineering' => 'ict-software',
        'Financial Accountant' => 'professional-services-accountinglegalconsulting',
        'Project Engineer' => 'construction',
        'Field Sales Agent' => 'distribution-retail',
        'Temporary Administrative Assistant' => 'professional-services-accountinglegalconsulting',
        'Business Analyst I - Remote/Work from Home (3 month Temporary Contract)' => 'ict-software',
        'Registered Nurse' => 'healthcare',
        'Server Administrator' => 'ict-software',
        'Restaurant Server' => 'tourism-hospitality',
        'Offshore Rig Electrician' => 'energy-petrochemicals',
        'Security Officer' => null,
    ];

    foreach ($cases as $title => $expected) {
        expect($industry->invoke(null, $title))->toBe($expected, "industry for '{$title}'");
    }

    expect($industry->invoke(null, 'Senior Process Engineer', 'onshore and offshore operations'))->toBe('energy-petrochemicals')
        ->and($employment->invoke(null, 'Temporary Administrative Assistant'))->toBe('temporary')
        ->and($employment->invoke(null, 'Business Analyst (3 month Temporary Contract)'))->toBe('temporary')
        ->and($employment->invoke(null, 'Accountant - Contract'))->toBe('contract')
        ->and($employment->invoke(null, 'Permanent Driver'))->toBe('permanent')
        ->and($employment->invoke(null, 'Accountant'))->toBeNull();
});

test('jobs:reclassify-local back-fills industry and employment type on old local rows', function () {
    $this->seed(IndustrySeeder::class);
    $a = JobListing::factory()->bare()->create(['source' => 'caribbeanjobs', 'source_job_id' => 'r1', 'title' => 'Senior Officer Platform Engineering', 'industry_id' => null, 'employment_type' => null]);
    $b = JobListing::factory()->bare()->create(['source' => 'caribbeanjobs', 'source_job_id' => 'r2', 'title' => 'Temporary Administrative Assistant', 'industry_id' => null, 'employment_type' => null]);
    $remote = JobListing::factory()->bare()->create(['source' => 'himalayas', 'source_job_id' => 'r3', 'title' => 'Registered Nurse', 'industry_id' => null]);

    $this->artisan('jobs:reclassify-local')->expectsOutputToContain('Reclassified 2 listing(s).')->assertSuccessful();

    expect($a->fresh()->industry->slug)->toBe('ict-software')
        ->and($b->fresh()->industry->slug)->toBe('professional-services-accountinglegalconsulting')
        ->and($b->fresh()->employment_type?->value)->toBe('temporary')
        ->and($remote->fresh()->industry_id)->toBeNull(); // remote boards are not touched
});

test('trinidadjob listings come from the public WordPress API with categories mapped to industries', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    config(['jobsources.remote.boards.trinidadjob.enabled' => true]);
    Http::fake([
        'trinidadjob.com/wp-json/wp/v2/job-listings*' => Http::response(file_get_contents(base_path('tests/Fixtures/localboards/trinidadjob-api.json'))),
    ]);

    $dtos = crawlAll(app(App\Services\JobSources\TrinidadJobSource::class));

    expect($dtos)->toHaveCount(2);
    $clerk = $dtos[0];
    expect($clerk->source)->toBe('trinidadjob')
        ->and($clerk->sourceJobId)->toBe('4227')
        ->and($clerk->title)->toBe('Facilities Administration Clerk')
        ->and($clerk->country)->toBe('TT')
        ->and($clerk->workArrangement)->toBe('on_premises')
        ->and($clerk->employmentType)->toBe('permanent')            // "Full Time"
        ->and($clerk->industrySlug)->toBe('construction')           // from the board's own category
        ->and($clerk->locationText)->toBe('Bon Air Gdns, Arouca, Trinidad and Tobago')
        ->and($clerk->postedAt)->toStartWith('2026-09-06')
        ->and($clerk->applyUrl)->toStartWith('https://trinidadjob.com/job/')
        ->and($clerk->description)->toContain('Facilities Administration Clerk')
        ->and($clerk->rawPayload['region'])->toBe('Arima/ Sangre Grande');

    expect($dtos[1]->seniority)->toBe('manager');                     // "Management/ Senior Management"

    // One short page (2 < per_page) means no second request.
    Http::assertSentCount(1);
});

test('trinidadjob skips listings the employer marked as filled', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    config(['jobsources.remote.boards.trinidadjob.enabled' => true]);
    $posts = json_decode(file_get_contents(base_path('tests/Fixtures/localboards/trinidadjob-api.json')), true);
    $posts[0]['meta']['_filled'] = 1;
    Http::fake(['trinidadjob.com/*' => Http::response(json_encode($posts))]);

    $dtos = crawlAll(app(App\Services\JobSources\TrinidadJobSource::class));

    expect($dtos)->toHaveCount(1)->and($dtos[0]->sourceJobId)->toBe('4228');
});
