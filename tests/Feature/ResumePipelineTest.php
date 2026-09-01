<?php

use App\Enums\ParseStatus;
use App\Jobs\ParseResumeJob;
use App\Livewire\ResumeUpload;
use App\Models\Resume;
use App\Models\User;
use Database\Seeders\SkillSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Tests\Fixtures\PdfBuilder;

use function Pest\Laravel\actingAs;

function verifiedUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

// ── Upload flow ─────────────────────────────────────────────────────

test('a valid pdf upload stores privately and queues parsing', function () {
    Storage::fake('local');
    Queue::fake();
    $user = verifiedUser();

    Livewire::actingAs($user)
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->create('cv.pdf', 200, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    $resume = Resume::query()->firstOrFail();

    expect($resume->user_id)->toBe($user->id)
        ->and($resume->parse_status)->toBe(ParseStatus::Pending);

    Storage::disk('local')->assertExists($resume->path);
    expect($resume->path)->toStartWith("resumes/{$user->id}/");

    Queue::assertPushed(ParseResumeJob::class);
});

test('uploads reject wrong types and oversized files', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::actingAs(verifiedUser())
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->create('cv.txt', 50, 'text/plain'))
        ->call('save')
        ->assertHasErrors('file');

    Livewire::actingAs(verifiedUser())
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->create('cv.pdf', 6000, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('file');

    // Extension/MIME mismatch (renamed .exe) is rejected too.
    Livewire::actingAs(verifiedUser())
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->create('cv.pdf', 50, 'application/octet-stream'))
        ->call('save')
        ->assertHasErrors('file');

    expect(Resume::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('uploads are rate limited per user', function () {
    Storage::fake('local');
    Queue::fake();
    config(['resume.upload_rate_limit' => 2]);
    $user = verifiedUser();

    foreach ([1, 2] as $attempt) {
        Livewire::actingAs($user)
            ->test(ResumeUpload::class)
            ->set('file', UploadedFile::fake()->create("cv{$attempt}.pdf", 50, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors();
    }

    Livewire::actingAs($user)
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->create('cv3.pdf', 50, 'application/pdf'))
        ->call('save')
        ->assertHasErrors('file');

    expect(Resume::count())->toBe(2);
});

// ── Signed, policy-gated download ───────────────────────────────────

test('resume downloads require a signed url and ownership', function () {
    Storage::fake('local');
    $owner = verifiedUser();
    $resume = Resume::factory()->for($owner)->create(['path' => "resumes/{$owner->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, PdfBuilder::fromLines(['Hello']));

    $signed = URL::signedRoute('resumes.download', ['resume' => $resume]);

    actingAs($owner)->get($signed)->assertOk();

    // Unsigned: 403.
    actingAs($owner)->get(route('resumes.download', $resume))->assertForbidden();

    // Signed but not the owner: 403.
    actingAs(verifiedUser())->get($signed)->assertForbidden();
});

// ── End-to-end parse: PDF and DOCX fixtures ─────────────────────────

test('a pdf resume parses end to end into profile data', function () {
    Storage::fake('local');
    $this->seed(SkillSeeder::class);
    $user = verifiedUser();

    $pdf = PdfBuilder::fromLines([
        'Jane Mohammed',
        'jane.mohammed@example.com',
        'Skills',
        'PHP, MySQL, MS Excel, Customer Service',
        'Work Experience',
        'Accounts Clerk at Island Finance',
        '2020 - Present',
    ]);

    $resume = Resume::factory()->for($user)->create(['path' => "resumes/{$user->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, $pdf);

    (new ParseResumeJob($resume))->handle(
        app(App\Services\Resume\TextExtractor::class),
        app(App\Services\Resume\ResumeStructurerInterface::class),
    );

    $resume->refresh();
    $profile = $user->profile()->firstOrFail();

    expect($resume->parse_status)->toBe(ParseStatus::Parsed)
        ->and($resume->extracted_text)->toContain('Jane Mohammed')
        ->and($profile->full_name)->toBe('Jane Mohammed')
        ->and($profile->skills()->pluck('name')->all())
        ->toContain('PHP', 'MySQL', 'Microsoft Excel', 'Customer Service')
        ->and($profile->workHistories()->count())->toBe(1);
});

test('a docx resume parses end to end', function () {
    Storage::fake('local');
    $this->seed(SkillSeeder::class);
    $user = verifiedUser();

    $word = new PhpWord;
    $section = $word->addSection();
    foreach ([
        'Marcus Charles',
        'marcus@example.com',
        'Skills',
        'Welding, Forklift Operation, HSE',
    ] as $line) {
        $section->addText($line);
    }

    $temp = tempnam(sys_get_temp_dir(), 'cv').'.docx';
    IOFactory::createWriter($word)->save($temp);

    $resume = Resume::factory()->for($user)->create([
        'path' => "resumes/{$user->id}/cv.docx",
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    Storage::disk('local')->put($resume->path, file_get_contents($temp));
    unlink($temp);

    (new ParseResumeJob($resume))->handle(
        app(App\Services\Resume\TextExtractor::class),
        app(App\Services\Resume\ResumeStructurerInterface::class),
    );

    expect($resume->refresh()->parse_status)->toBe(ParseStatus::Parsed)
        ->and($user->profile()->firstOrFail()->skills()->pluck('name')->all())
        ->toContain('Welding', 'Forklift Operation', 'Occupational Health & Safety');
});

test('re-parsing never overwrites user-edited data', function () {
    Storage::fake('local');
    $this->seed(SkillSeeder::class);
    $user = verifiedUser();

    // User has already curated their profile.
    $profile = $user->profile()->create([
        'full_name' => 'My Chosen Name',
        'summary' => 'My own summary.',
    ]);
    $work = $profile->workHistories()->create([
        'employer' => 'Island Finance',
        'title' => 'Accounts Clerk',
        'description' => 'MY EDITED DESCRIPTION',
        'is_user_edited' => true,
    ]);

    $pdf = PdfBuilder::fromLines([
        'Jane Mohammed',
        'Work Experience',
        'Accounts Clerk at Island Finance',
        '2018 - 2020',
        'Parser-generated description that must not win.',
    ]);

    $resume = Resume::factory()->for($user)->create(['path' => "resumes/{$user->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, $pdf);

    (new ParseResumeJob($resume))->handle(
        app(App\Services\Resume\TextExtractor::class),
        app(App\Services\Resume\ResumeStructurerInterface::class),
    );

    expect($profile->refresh()->full_name)->toBe('My Chosen Name')
        ->and($profile->summary)->toBe('My own summary.')
        ->and($work->refresh()->description)->toBe('MY EDITED DESCRIPTION')
        ->and($profile->workHistories()->count())->toBe(1); // no duplicate row
});

test('deleting a resume removes the stored file', function () {
    Storage::fake('local');
    $user = verifiedUser();
    $resume = Resume::factory()->for($user)->create(['path' => "resumes/{$user->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, 'data');

    Livewire::actingAs($user)
        ->test(ResumeUpload::class)
        ->call('delete', $resume->id);

    Storage::disk('local')->assertMissing($resume->path);
    expect(Resume::count())->toBe(0);
});

test('account deletion purges resume files', function () {
    Storage::fake('local');
    $user = verifiedUser();
    $resume = Resume::factory()->for($user)->create(['path' => "resumes/{$user->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, 'data');

    actingAs($user)->delete('/profile', ['password' => 'password']);

    Storage::disk('local')->assertMissing($resume->path);
    expect(User::count())->toBe(0)->and(Resume::count())->toBe(0);
});

test('resume and review pages render for verified users', function () {
    $user = verifiedUser();

    actingAs($user)->get('/resume')->assertOk()->assertSee('Upload your resume');
    actingAs($user)->get('/profile/review')->assertOk()->assertSee('Your details');
});
