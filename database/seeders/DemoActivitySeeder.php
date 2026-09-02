<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ParseStatus;
use App\Models\Application;
use App\Models\JobListing;
use App\Models\Skill;
use App\Models\User;
use App\Services\Advisory\SkillGapPlanner;
use App\Services\Engagement\AchievementService;
use App\Services\Matching\MatchRecomputeService;
use App\Support\SimplePdf;
use Database\Seeders\Concerns\DemoOnly;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns the demo accounts into people mid-search: filled profiles,
 * preferences, computed matches, a gap plan, tracked applications and
 * earned milestones — so a tester sees the app working, not an empty
 * state. Idempotent; `php artisan demo:reset` wipes and re-runs it.
 * Requires DemoUserSeeder + DemoJobSeeder (and reference data) first.
 */
class DemoActivitySeeder extends Seeder
{
    use DemoOnly;

    public function run(): void
    {
        if (! self::demoAllowed()) {
            return;
        }

        $this->aaliyah();
        $this->marcus();
    }

    /** Office / customer-service job seeker, three years in, active search. */
    private function aaliyah(): void
    {
        $user = User::query()->where('email', 'demo@pathwaytt.test')->first();
        if ($user === null) {
            return;
        }

        $profile = $user->profile()->updateOrCreate([], [
            'full_name' => 'Aaliyah Mohammed',
            'phone' => '868-555-0142',
            'region' => 'San Juan',
            'summary' => 'Customer service professional with three years across telecoms and retail, comfortable with CRM systems, ticketing and basic bookkeeping. Looking to move into an office or remote support role with room to grow.',
            'years_experience' => 3,
            'highest_education_level' => 'cape',
            'has_nis' => true,
            'has_bir' => true,
            'has_drivers_permit' => true,
            'has_police_certificate' => false,
            'willing_to_relocate' => false,
        ]);

        $this->attachSkills($profile, [
            'Customer Service' => 5, 'Call Handling' => 4, 'Live Chat Support' => 4, 'CRM Software' => 3,
            'Microsoft Excel' => 3, 'Microsoft Word' => 4, 'Data Entry' => 4, 'Communication' => 5,
            'Teamwork' => 4, 'Time Management' => 4, 'Bookkeeping' => 2,
        ]);

        $profile->workHistories()->updateOrCreate(
            ['employer' => 'Island Telecom Ltd', 'title' => 'Customer Service Representative'],
            ['industry' => 'Telecommunications', 'started_at' => '2023-02-01', 'ended_at' => null, 'is_current' => true,
                'description' => 'Handle 60+ customer contacts a day across phone and live chat; resolve billing and service issues; maintain CRM records. Top-quartile customer satisfaction score for six consecutive months.', 'is_user_edited' => true],
        );
        $profile->workHistories()->updateOrCreate(
            ['employer' => 'Westside Retail Ltd', 'title' => 'Sales & Office Assistant'],
            ['industry' => 'Retail', 'started_at' => '2021-06-01', 'ended_at' => '2023-01-31', 'is_current' => false,
                'description' => 'Front-of-store sales, daily cash reconciliation, supplier invoice filing and data entry in QuickBooks.', 'is_user_edited' => true],
        );

        $profile->educations()->updateOrCreate(
            ['institution' => 'St. James Secondary School', 'qualification_type' => 'cape'],
            ['field' => 'Management of Business, Accounting, Communication Studies', 'completed_at' => '2021-06-30', 'is_user_edited' => true],
        );
        $profile->educations()->updateOrCreate(
            ['institution' => 'St. James Secondary School', 'qualification_type' => 'csec'],
            ['field' => '7 passes incl. English A and Mathematics', 'completed_at' => '2019-06-30', 'is_user_edited' => true],
        );

        $profile->certifications()->updateOrCreate(
            ['name' => 'Microsoft Office Specialist: Excel Associate'],
            ['issuer' => 'Microsoft', 'issued_at' => '2024-03-15', 'is_user_edited' => true],
        );

        $user->jobPreference()->updateOrCreate([], [
            'industry_id' => \App\Models\Industry::query()->where('slug', 'bpo-contact-centre')->value('id'),
            'work_arrangements' => ['on_premises', 'hybrid_local', 'remote_international'],
            'employment_types' => ['permanent', 'contract'],
            'seniority' => 'entry',
            'min_salary_cents' => 550000,
            'min_salary_currency' => 'TTD',
            'min_salary_period' => 'monthly',
        ]);

        $this->resume($user, [
            'Aaliyah Mohammed', 'San Juan, Trinidad | 868-555-0142 | aaliyah.demo@example.com',
            'Professional Summary',
            'Customer service professional with three years across telecoms and retail.',
            'Skills',
            'Customer Service, Call Handling, Live Chat Support, CRM Software, Microsoft Excel, Microsoft Word, Data Entry',
            'Experience',
            'Customer Service Representative at Island Telecom Ltd, Feb 2023 - Present',
            'Sales & Office Assistant at Westside Retail Ltd, Jun 2021 - Jan 2023',
            'Education',
            'CAPE - St. James Secondary School, 2021',
        ]);

        $this->track($user, 'Accounts Payable Clerk', ApplicationStatus::Applied, appliedDaysAgo: 2,
            notes: 'Applied through the company site. Follow up with HR (Ms. Ramdass) on Friday.');
        $this->track($user, 'Call Centre Agent (Evening Shift, US Clients)', ApplicationStatus::Interviewing, appliedDaysAgo: 6,
            notes: 'Phone screen done. In-person interview Tuesday 10am, Chaguanas — bring police certificate receipt.');
        $this->track($user, 'Customer Support Representative (Remote, Worldwide)', ApplicationStatus::Saved,
            notes: 'Need to open a Wise account before applying.');

        $this->compute($user);
    }

    /** Energy-sector tradesman, seven years, looking for a step up. */
    private function marcus(): void
    {
        $user = User::query()->where('email', 'marcus@pathwaytt.test')->first();
        if ($user === null) {
            return;
        }

        $profile = $user->profile()->updateOrCreate([], [
            'full_name' => 'Marcus Charles',
            'phone' => '868-555-0177',
            'region' => 'Point Fortin',
            'summary' => 'Welder and pipefitter with seven years on onshore energy and fabrication sites. 6G certified, permit-to-work trained, safety-first.',
            'years_experience' => 7,
            'highest_education_level' => 'certificate',
            'has_nis' => true,
            'has_bir' => true,
            'has_drivers_permit' => true,
            'has_police_certificate' => true,
            'willing_to_relocate' => true,
        ]);

        $this->attachSkills($profile, [
            'Welding' => 5, 'Pipefitting' => 5, 'Blueprint Reading' => 4, 'Rigging' => 3, 'Scaffolding' => 3,
            'Occupational Health & Safety' => 4, 'Permit-to-Work Systems' => 4, 'Forklift Operation' => 3, 'Mechanical Maintenance' => 3,
        ]);

        $profile->workHistories()->updateOrCreate(
            ['employer' => 'Southern Fabricators Ltd', 'title' => 'Welder / Pipefitter'],
            ['industry' => 'Energy', 'started_at' => '2019-04-01', 'ended_at' => null, 'is_current' => true,
                'description' => 'Structural and pipe welding for plant maintenance contracts; shutdown work at Point Lisas sites.', 'is_user_edited' => true],
        );
        $profile->workHistories()->updateOrCreate(
            ['employer' => 'Gulf Marine Services', 'title' => 'Apprentice Welder'],
            ['industry' => 'Energy', 'started_at' => '2017-01-15', 'ended_at' => '2019-03-31', 'is_current' => false,
                'description' => 'Apprenticeship covering SMAW and GTAW welding, fitting and yard safety.', 'is_user_edited' => true],
        );
        $profile->educations()->updateOrCreate(
            ['institution' => 'NESC Technical Institute', 'qualification_type' => 'certificate'],
            ['field' => 'Welding Technology', 'completed_at' => '2017-12-15', 'is_user_edited' => true],
        );
        $profile->certifications()->updateOrCreate(
            ['name' => '6G Pipe Welding Certification'],
            ['issuer' => 'Southern Fabricators Ltd (third-party tested)', 'issued_at' => '2022-08-01', 'is_user_edited' => true],
        );

        $user->jobPreference()->updateOrCreate([], [
            'industry_id' => \App\Models\Industry::query()->where('slug', 'energy-petrochemicals')->value('id'),
            'work_arrangements' => ['on_premises'],
            'employment_types' => ['permanent', 'contract'],
            'seniority' => 'mid',
            'min_salary_cents' => 1200000,
            'min_salary_currency' => 'TTD',
            'min_salary_period' => 'monthly',
        ]);

        $this->resume($user, [
            'Marcus Charles', 'Point Fortin, Trinidad | 868-555-0177',
            'Skills', 'Welding, Pipefitting, Blueprint Reading, Rigging, Scaffolding, Permit-to-Work Systems',
            'Experience', 'Welder / Pipefitter at Southern Fabricators Ltd, Apr 2019 - Present',
            'Apprentice Welder at Gulf Marine Services, Jan 2017 - Mar 2019',
            'Education', 'Certificate in Welding Technology - NESC Technical Institute, 2017',
        ]);

        $this->track($user, 'Pipefitter — Plant Shutdown (3-month contract)', ApplicationStatus::Saved);

        $this->compute($user);
    }

    private function attachSkills($profile, array $skills): void
    {
        $ids = Skill::query()->whereIn('name', array_keys($skills))->pluck('id', 'name');
        $sync = [];
        foreach ($skills as $name => $proficiency) {
            if (isset($ids[$name])) {
                $sync[$ids[$name]] = ['proficiency' => $proficiency, 'evidence_source' => 'self_reported', 'is_user_edited' => true];
            }
        }
        $profile->skills()->syncWithoutDetaching($sync);
    }

    private function track(User $user, string $demoTitle, ApplicationStatus $status, ?int $appliedDaysAgo = null, ?string $notes = null): void
    {
        $listing = JobListing::query()->where('source', 'demo')->where('title', '[DEMO] '.$demoTitle)->first();
        if ($listing === null) {
            return;
        }

        Application::query()->updateOrCreate(
            ['user_id' => $user->id, 'job_listing_id' => $listing->id],
            ['status' => $status, 'applied_at' => $appliedDaysAgo !== null ? now()->subDays($appliedDaysAgo) : null, 'notes' => $notes],
        );
    }

    /** A real, parseable PDF on the private disk so the resume screen and "Resume in" milestone are live. */
    private function resume(User $user, array $lines): void
    {
        if ($user->resumes()->exists()) {
            return;
        }

        $path = "resumes/{$user->id}/".Str::uuid().'.pdf';
        $pdf = SimplePdf::fromLines($lines);
        Storage::disk(config('resume.disk'))->put($path, $pdf);

        $user->resumes()->create([
            'original_filename' => Str::slug(explode(' ', $lines[0])[0]).'-resume.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf),
            'parse_status' => ParseStatus::Parsed,
            'extracted_text' => implode("\n", $lines),
            'parsed_at' => now()->subDays(3),
        ]);
    }

    /** Scores, plan and milestones — synchronously, so the state is complete when seeding ends. */
    private function compute(User $user): void
    {
        app(MatchRecomputeService::class)->recomputeForUser($user);

        if (! $user->skillGapPlans()->exists()) {
            app(SkillGapPlanner::class)->generate($user);
        }

        app(AchievementService::class)->evaluate($user);
    }
}
