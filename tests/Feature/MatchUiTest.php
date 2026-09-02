<?php

use App\Livewire\JobPreferences;
use App\Livewire\MatchList;
use App\Livewire\ResumeUpload;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\JobMatch;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\Matching\MatchRecomputeService;
use Database\Seeders\IndustrySeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\PdfBuilder;

function matchUiUser(array $skillIds = []): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $profile = Profile::factory()->for($user)->create(['years_experience' => 5]);

    foreach ($skillIds as $id) {
        $profile->skills()->attach($id, ['evidence_source' => 'self_reported', 'is_user_edited' => true]);
    }

    return $user;
}

function listingRequiring(string $title, array $skillIds): JobListing
{
    $listing = JobListing::factory()->bare()->create(['title' => $title]);
    $listing->skills()->sync(collect($skillIds)->mapWithKeys(fn ($id) => [$id => ['is_required' => true, 'weight' => 1]])->all());

    return $listing;
}

test('matches are ranked by score with explanations and what is missing', function () {
    [$a, $b, $c] = Skill::factory()->count(3)->create();
    $user = matchUiUser([$a->id, $b->id]);

    listingRequiring('Perfect Fit Role', [$a->id, $b->id]); // 100
    listingRequiring('Partial Fit Role', [$a->id, $c->id]); // 50, capped 80
    JobListing::factory()->usOnly()->create(['title' => 'US Only Role']);

    app(MatchRecomputeService::class)->recomputeForUser($user);

    $this->actingAs($user)->get('/matches')
        ->assertOk()
        ->assertSeeInOrder(['Perfect Fit Role', 'Partial Fit Role'])
        ->assertSee('You have 2 of 2 required skills')
        ->assertSee("What you're missing", false)
        ->assertSee($c->name)
        ->assertSee('Why this score?')
        ->assertSee('1 not eligible')
        ->assertDontSee('US Only Role');

    Livewire::actingAs($user)
        ->test(MatchList::class)
        ->set('showIneligible', true)
        ->assertSee('US Only Role')
        ->assertSee('Restricted to applicants in US');
});

test('the page pivots to advisory mode when the best score is below the threshold', function () {
    [$a, $b, $c] = Skill::factory()->count(3)->create();
    $user = matchUiUser([$a->id]);

    listingRequiring('Hard Role One', [$a->id, $b->id, $c->id]); // 33
    listingRequiring('Hard Role Two', [$b->id, $c->id]);         // 0

    app(MatchRecomputeService::class)->recomputeForUser($user);

    $this->actingAs($user)->get('/matches')
        ->assertOk()
        ->assertSee('below the 55 threshold')
        ->assertSee($b->name)
        ->assertSee('required by 2 listings')
        ->assertSee('Hard Role One'); // low matches are still listed under the callout
});

test('zero eligible listings still shows a next action, never an empty page', function () {
    $user = matchUiUser();
    JobListing::factory()->usOnly()->create(['title' => 'US Only Role']);

    app(MatchRecomputeService::class)->recomputeForUser($user);

    $this->actingAs($user)->get('/matches')
        ->assertOk()
        ->assertSee('No eligible listings match your profile yet')
        ->assertSee('Add it to your profile');
});

test('the job page shows the user\'s score breakdown and tracker', function () {
    $skill = Skill::factory()->create();
    $user = matchUiUser([$skill->id]);
    $job = listingRequiring('Scored Role', [$skill->id]);
    app(MatchRecomputeService::class)->recomputeForUser($user);

    $this->actingAs($user)->get(route('jobs.show', $job))
        ->assertOk()
        ->assertSee('Your match')
        ->assertSee('100')
        ->assertSee('Why this score?')
        ->assertSee('Required skills')
        ->assertSee('Save job');

    $unscored = JobListing::factory()->create(['title' => 'Unscored Role']);
    $this->actingAs($user)->get(route('jobs.show', $unscored))->assertOk()->assertSee('Not scored yet');
});

test('end to end: upload a resume, set preferences, see ranked matches', function () {
    Storage::fake('local');
    $this->seed([IndustrySeeder::class, SkillSeeder::class]);

    $ict = Industry::query()->where('slug', 'ict-software')->firstOrFail();
    $php = Skill::query()->where('slug', 'php')->firstOrFail();
    $mysql = Skill::query()->where('slug', 'mysql')->firstOrFail();
    $react = Skill::query()->where('slug', 'react')->firstOrFail();

    $dev = JobListing::factory()->bare()->create(['title' => 'Junior PHP Developer', 'industry_id' => $ict->id, 'seniority' => 'entry']);
    $dev->skills()->sync([$php->id => ['is_required' => true, 'weight' => 1], $mysql->id => ['is_required' => true, 'weight' => 1]]);
    $front = JobListing::factory()->bare()->create(['title' => 'React Front-end Developer', 'industry_id' => $ict->id]);
    $front->skills()->sync([$react->id => ['is_required' => true, 'weight' => 1]]);

    $user = User::factory()->create(['email_verified_at' => now()]);

    // 1. Upload (queue is sync in tests: parse + recompute run inline)
    $pdf = PdfBuilder::fromLines([
        'Jane Mohammed', 'jane@example.com',
        'Skills', 'PHP, MySQL, HTML',
        'Experience', 'Web Developer at Local Shop', '2021 - 2024',
    ]);

    Livewire::actingAs($user)
        ->test(ResumeUpload::class)
        ->set('file', UploadedFile::fake()->createWithContent('cv.pdf', $pdf))
        ->call('save')
        ->assertHasNoErrors();

    expect($user->profile()->firstOrFail()->skills()->pluck('slug')->all())->toContain('php', 'mysql');

    // 2. Preferences
    Livewire::actingAs($user)
        ->test(JobPreferences::class)
        ->set('industry_id', $ict->id)
        ->set('work_arrangements', ['on_premises'])
        ->call('save')
        ->assertHasNoErrors();

    // 3. Matches
    $this->actingAs($user)->get('/matches')
        ->assertOk()
        ->assertSeeInOrder(['Junior PHP Developer', 'React Front-end Developer']);

    $top = JobMatch::query()->where('user_id', $user->id)->where('job_listing_id', $dev->id)->firstOrFail();
    expect($top->is_eligible)->toBeTrue()
        ->and($top->score)->toBe(100)
        ->and($top->score_breakdown['summary'])->toContain('In your target industry');
});
