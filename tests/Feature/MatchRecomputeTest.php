<?php

use App\Jobs\ParseResumeJob;
use App\Jobs\RecomputeAllMatchesJob;
use App\Jobs\RecomputeUserMatchesJob;
use App\Livewire\JobPreferences;
use App\Livewire\ProfileReview;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\Profile;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use App\Services\Matching\MatchRecomputeService;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\PdfBuilder;

function recomputeUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    Profile::factory()->for($user)->create(['years_experience' => 5]);

    return $user;
}

test('recompute scores every open listing and stores hard-filtered ones as ineligible', function () {
    $user = recomputeUser();
    $skill = Skill::factory()->create();
    $user->profile()->first()->skills()->attach($skill->id, ['evidence_source' => 'self_reported', 'is_user_edited' => true]);

    $good = JobListing::factory()->bare()->create();
    $good->skills()->attach($skill->id, ['is_required' => true, 'weight' => 1]);
    $other = JobListing::factory()->bare()->create();
    $usOnly = JobListing::factory()->usOnly()->create();
    JobListing::factory()->create(['is_active' => false]);
    JobListing::factory()->closed()->create();

    $scored = app(MatchRecomputeService::class)->recomputeForUser($user);

    expect($scored)->toBe(3)->and(JobMatch::count())->toBe(3);

    $match = JobMatch::query()->where('job_listing_id', $good->id)->firstOrFail();
    expect($match->is_eligible)->toBeTrue()
        ->and($match->score)->toBe(100)
        ->and($match->score_breakdown['components'])->not->toBeEmpty()
        ->and($match->computed_at)->not->toBeNull();

    $ineligible = JobMatch::query()->where('job_listing_id', $usOnly->id)->firstOrFail();
    expect($ineligible->is_eligible)->toBeFalse()
        ->and($ineligible->score)->toBe(0)
        ->and($ineligible->ineligibility_reason)->toContain('Restricted to applicants in US');

    // Re-running never duplicates; a deactivated listing drops out.
    $other->update(['is_active' => false]);
    app(MatchRecomputeService::class)->recomputeForUser($user);

    expect(JobMatch::count())->toBe(2)
        ->and(JobMatch::query()->where('job_listing_id', $other->id)->exists())->toBeFalse();
});

test('saving preferences queues a recompute for that user', function () {
    Queue::fake();
    $user = recomputeUser();

    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->set('seniority', 'mid')
        ->call('save')
        ->assertHasNoErrors();

    Queue::assertPushed(RecomputeUserMatchesJob::class, fn (RecomputeUserMatchesJob $job) => $job->userId === $user->id);
});

test('profile edits queue a recompute for that user', function () {
    Queue::fake();
    $user = recomputeUser();
    $skill = Skill::factory()->create();

    Livewire::actingAs($user)
        ->test(ProfileReview::class)
        ->call('addSkill', $skill->id);

    Queue::assertPushed(RecomputeUserMatchesJob::class, fn (RecomputeUserMatchesJob $job) => $job->userId === $user->id);
});

test('resume parsing queues a recompute once the profile is updated', function () {
    Queue::fake();
    Storage::fake('local');
    $this->seed(SkillSeeder::class);
    $user = recomputeUser();

    $resume = Resume::factory()->for($user)->create(['path' => "resumes/{$user->id}/cv.pdf"]);
    Storage::disk('local')->put($resume->path, PdfBuilder::fromLines(['Jane Mohammed', 'Skills', 'PHP, MySQL']));

    (new ParseResumeJob($resume))->handle(
        app(App\Services\Resume\TextExtractor::class),
        app(App\Services\Resume\ResumeStructurerInterface::class),
    );

    Queue::assertPushed(RecomputeUserMatchesJob::class, fn (RecomputeUserMatchesJob $job) => $job->userId === $user->id);
});

test('job:sync with changed listings fans out a recompute to every user with a profile', function () {
    Queue::fake();
    Storage::fake('local');
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);

    recomputeUser();
    recomputeUser();
    User::factory()->create(); // no profile → nothing to score

    Storage::disk('local')->put('import/jobs/batch.csv', file_get_contents(base_path('docs/job-import-template.csv')));
    $this->artisan('job:sync')->assertSuccessful();

    Queue::assertPushed(RecomputeAllMatchesJob::class);

    (new RecomputeAllMatchesJob)->handle();
    Queue::assertPushed(RecomputeUserMatchesJob::class, 2);

    // A run that changes nothing queues nothing.
    Queue::fake();
    $this->artisan('job:sync')->assertSuccessful();
    Queue::assertNotPushed(RecomputeAllMatchesJob::class);
});

test('the queued job tolerates a user deleted while it waited', function () {
    $user = recomputeUser();
    $id = $user->id;
    $user->delete();

    (new RecomputeUserMatchesJob($id))->handle(app(MatchRecomputeService::class));

    expect(JobMatch::count())->toBe(0);
});
