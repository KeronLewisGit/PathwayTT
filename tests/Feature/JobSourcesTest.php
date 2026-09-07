<?php

use App\DTOs\JobDto;
use App\Models\Company;
use App\Models\JobListing;
use App\Models\JobSyncRun;
use App\Services\JobSources\CsvImportSource;
use App\Services\JobSources\JobIngestor;
use App\Support\Money;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Support\Facades\Storage;

// ── Money ────────────────────────────────────────────────────────────

test('money helper converts dollars to integer cents and back', function () {
    expect(Money::toCents('9,500'))->toBe(950000)
        ->and(Money::toCents('1200.50'))->toBe(120050)
        ->and(Money::toCents(''))->toBeNull()
        ->and(Money::toCents('abc'))->toBeNull()
        ->and(Money::format(950000))->toBe('9,500')
        ->and(Money::format(120050))->toBe('1,200.50')
        ->and(Money::convert(100000, 'USD', 'TTD', 6.8))->toBe(680000)
        ->and(Money::convert(680000, 'TTD', 'USD', 6.8))->toBe(100000)
        ->and(Money::convert(100, 'USD', 'EUR', 6.8))->toBeNull();
});

// ── CSV parsing ──────────────────────────────────────────────────────

test('the shipped csv template parses into normalized job dtos', function () {
    $csv = file_get_contents(base_path('docs/job-import-template.csv'));

    $dtos = iterator_to_array((new CsvImportSource)->parse($csv, 'job-import-template.csv'), false);

    expect($dtos)->toHaveCount(2)
        ->and($dtos[0])->toBeInstanceOf(JobDto::class)
        ->and($dtos[0]->source)->toBe('csv')
        ->and($dtos[0]->title)->toBe('Accounts Clerk')
        ->and($dtos[0]->industrySlug)->toBe('financial-services-insurance')
        ->and($dtos[0]->country)->toBe('TT')
        ->and($dtos[0]->salaryMinCents)->toBe(600000)
        ->and($dtos[0]->salaryMaxCents)->toBe(850000)
        ->and($dtos[0]->salaryCurrency)->toBe('TTD')
        ->and($dtos[0]->requiredSkills)->toBe(['bookkeeping', 'microsoft-excel'])
        ->and($dtos[0]->preferredSkills)->toBe(['accounts-payable', 'quickbooks'])
        ->and($dtos[0]->requirements)->toHaveCount(2)
        ->and($dtos[1]->workArrangement)->toBe('remote_international')
        ->and($dtos[1]->requiredOverlapHours)->toBe(4)
        ->and($dtos[1]->salaryCurrency)->toBe('USD');

    // No source_job_id in the template -> a stable hash so re-imports upsert.
    expect($dtos[0]->sourceJobId)->toBe(sha1('job-import-template.csv|Accounts Clerk|Example Finance Ltd'));
});

test('csv import rejects files missing required columns', function () {
    $csv = "company,description\nAcme,Nothing\n";

    iterator_to_array((new CsvImportSource)->parse($csv, 'bad.csv'), false);
})->throws(RuntimeException::class, 'missing required column "title"');

// ── Ingestion ────────────────────────────────────────────────────────

test('ingestor upserts on source + source_job_id and resolves skills via aliases', function () {
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);
    $ingestor = app(JobIngestor::class);

    $dto = new JobDto(
        source: 'csv',
        sourceJobId: 'abc-1',
        title: 'Junior Developer',
        companyName: 'Acme Software Ltd',
        industrySlug: 'ict-software',
        workArrangement: 'on_premises',
        requiredSkills: ['JS', 'mysql', 'Not A Real Skill'],
        preferredSkills: ['Postgres', 'JS'],
        salaryMinCents: 900000,
        salaryCurrency: 'TTD',
        applyUrl: 'https://example.com/apply',
    );

    expect($ingestor->ingest($dto))->toBe(['created' => true, 'changed' => true]);

    // Re-ingesting an identical feed is a no-op: no "updated" count, no recompute fan-out.
    expect($ingestor->ingest($dto))->toBe(['created' => false, 'changed' => false]);

    $listing = JobListing::query()->firstOrFail();
    expect($listing->industry->slug)->toBe('ict-software')
        ->and($listing->company)->toBeInstanceOf(Company::class)
        ->and($listing->company->name)->toBe('Acme Software Ltd')
        ->and($listing->salary_min_cents)->toBe(900000);

    $skills = $listing->skills()->get()->keyBy('slug');
    expect($skills)->toHaveCount(3) // JS→javascript, mysql, Postgres→postgresql; unknown skipped
        ->and($skills['javascript']->pivot->is_required)->toBeTruthy()
        ->and($skills['mysql']->pivot->is_required)->toBeTruthy()
        ->and($skills['postgresql']->pivot->is_required)->toBeFalsy();

    // Same source + id with a new title → update, not a duplicate.
    $dto->title = 'Junior PHP Developer';
    $dto->requiredSkills = ['PHP'];
    $dto->preferredSkills = [];

    expect($ingestor->ingest($dto))->toBe(['created' => false, 'changed' => true])
        ->and(JobListing::count())->toBe(1)
        ->and($listing->fresh()->title)->toBe('Junior PHP Developer')
        ->and($listing->skills()->pluck('slug')->all())->toBe(['php'])
        ->and(Company::count())->toBe(1);
});

test('ingestor fits over-long strings to their columns and keeps non-Latin employers apart', function () {
    $ingestor = app(JobIngestor::class);
    $countries = implode(', ', array_fill(0, 60, 'Trinidad and Tobago'));

    $ingestor->ingest(new JobDto(
        source: 'himalayas', sourceJobId: 'long-1', title: str_repeat('Engineer ', 40),
        companyName: '北京科技有限公司', locationText: 'Remote — '.$countries, salaryCurrency: 'US Dollars',
    ));
    $ingestor->ingest(new JobDto(
        source: 'himalayas', sourceJobId: 'long-2', title: 'Second', companyName: 'شركة التقنية',
    ));

    $listing = JobListing::query()->where('source_job_id', 'long-1')->firstOrFail();
    expect(mb_strlen($listing->title))->toBeLessThanOrEqual(255)
        ->and(mb_strlen($listing->location_text))->toBeLessThanOrEqual(255)
        ->and($listing->location_text)->toStartWith('Remote — Trinidad')
        ->and($listing->salary_currency)->toBeNull()   // "US Dollars" is not a 3-letter code
        ->and(Company::count())->toBe(2)                 // slugs never collapse onto ''
        ->and(Company::query()->pluck('slug')->filter(fn ($s) => $s === '')->count())->toBe(0);
});

test('csv import names the file, line and column for an invalid enum or date', function () {
    $header = 'title,work_arrangement,employment_type,posted_at';

    expect(fn () => iterator_to_array((new CsvImportSource)->parse("{$header}\nClerk,on_premises,Full-time,2026-09-01\n", 'jobs.csv'), false))
        ->toThrow(RuntimeException::class, 'jobs.csv line 2: employment_type "Full-time" is not one of: permanent, contract, temporary');

    expect(fn () => iterator_to_array((new CsvImportSource)->parse("{$header}\nClerk,on_premises,permanent,31/13/2026\n", 'jobs.csv'), false))
        ->toThrow(RuntimeException::class, 'jobs.csv line 2: posted_at "31/13/2026" is not a date');

    $ok = iterator_to_array((new CsvImportSource)->parse("{$header}\nClerk,On-Premises,Permanent,02/09/2026\n", 'jobs.csv'), false);
    expect($ok[0]->workArrangement)->toBe('on_premises')
        ->and($ok[0]->employmentType)->toBe('permanent')
        ->and($ok[0]->postedAt)->toStartWith('2026-09-02');
});

test('board descriptions keep text after a literal "<" and drop script blocks', function () {
    $text = new ReflectionMethod(App\Services\JobSources\RemoteBoardSource::class, 'text');

    expect($text->invoke(null, '<p>C++ dev, &lt;3 years exp required. Salary &lt; 5000 &gt; 4000.</p><script>alert(1)</script><p>Apply now &amp; join.</p>'))
        ->toBe("C++ dev, <3 years exp required. Salary < 5000 > 4000.\nApply now & join.");
});

test('array-valued query strings are dropped instead of crashing #[Url] properties', function () {
    $user = App\Models\User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)->get('/jobs?q[]=x&industry[]=1&arrangement=remote_international')->assertOk();
    $this->actingAs($user)->get('/matches?showIneligible[]=1')->assertOk();
    $this->actingAs($user)->get('/applications?status[]=saved')->assertOk();
});

// ── job:sync command ─────────────────────────────────────────────────

test('job:sync ingests csv inbox files, archives them and logs a run per source', function () {
    Storage::fake('local');
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);

    Storage::disk('local')->put('import/jobs/batch.csv', file_get_contents(base_path('docs/job-import-template.csv')));

    $this->artisan('job:sync')->assertSuccessful();

    expect(JobListing::count())->toBe(2)
        ->and(JobListing::where('source', 'csv')->count())->toBe(2);

    $csvRun = JobSyncRun::query()->where('source', 'csv')->firstOrFail();
    expect($csvRun->fetched_count)->toBe(2)
        ->and($csvRun->created_count)->toBe(2)
        ->and($csvRun->updated_count)->toBe(0)
        ->and($csvRun->error)->toBeNull()
        ->and($csvRun->finished_at)->not->toBeNull();

    // Manual source is always-on and logged even though it fetches nothing.
    expect(JobSyncRun::query()->where('source', 'manual')->exists())->toBeTrue();

    // File moved out of the inbox into the archive.
    Storage::disk('local')->assertMissing('import/jobs/batch.csv');
    expect(Storage::disk('local')->files('import/jobs/processed'))->toHaveCount(1);

    // Re-importing the same file is neither a duplicate nor a change: nothing
    // differs, so nothing counts as updated and no recompute fans out.
    Storage::disk('local')->put('import/jobs/batch.csv', file_get_contents(base_path('docs/job-import-template.csv')));
    $this->artisan('job:sync', ['--source' => 'csv'])->assertSuccessful();

    $rerun = JobSyncRun::query()->where('source', 'csv')->latest('id')->first();
    expect(JobListing::count())->toBe(2)
        ->and($rerun->fetched_count)->toBe(2)
        ->and($rerun->updated_count)->toBe(0);
});

test('job:sync records a source failure without aborting the run', function () {
    Storage::fake('local');

    Storage::disk('local')->put('import/jobs/broken.csv', "company,description\nAcme,No title column\n");

    $this->artisan('job:sync')->assertFailed();

    $run = JobSyncRun::query()->where('source', 'csv')->firstOrFail();
    expect($run->error)->toContain('missing required column "title"')
        ->and($run->finished_at)->not->toBeNull()
        ->and(JobListing::count())->toBe(0);
});

test('admins see the job-sync dashboard with run history', function () {
    $admin = App\Models\User::factory()->create(['email_verified_at' => now()]);
    $admin->forceFill(['is_admin' => true])->save();

    JobSyncRun::query()->create([
        'source' => 'csv', 'started_at' => now()->subMinute(), 'finished_at' => now(),
        'fetched_count' => 3, 'created_count' => 2, 'updated_count' => 1,
        'error' => 'CSV broken.csv is missing required column "title".',
    ]);

    $this->actingAs($admin)
        ->get('/admin/job-sync-runs')
        ->assertOk()
        ->assertSee('Upload CSV')
        ->assertSee('Run sync now')
        ->assertSee('missing required column');
});

test('job:sync --source runs a single adapter', function () {
    Storage::fake('local');

    $this->artisan('job:sync', ['--source' => 'manual'])->assertSuccessful();

    expect(JobSyncRun::query()->pluck('source')->all())->toBe(['manual']);
});
