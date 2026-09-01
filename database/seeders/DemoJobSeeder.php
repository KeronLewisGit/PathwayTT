<?php

namespace Database\Seeders;

use App\Enums\EmploymentType;
use App\Enums\GeoEligibility;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * DEMO DATA — local development only.
 *
 * These are NOT real job listings. Every record carries source = "demo" and
 * a [DEMO] title prefix so they can never be mistaken for, or mixed into,
 * real sourced jobs. DatabaseSeeder only invokes this in the local env.
 */
class DemoJobSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $ict = Industry::query()->where('slug', 'ict-software')->first();
        $bpo = Industry::query()->where('slug', 'bpo-contact-centre')->first();
        $finance = Industry::query()->where('slug', 'financial-services-insurance')->first();

        $jobs = [
            [
                'title' => '[DEMO] Junior Web Developer',
                'company_name' => 'Demo Local Software Ltd',
                'industry_id' => $ict?->id,
                'work_arrangement' => WorkArrangement::OnPremises,
                'employment_type' => EmploymentType::Permanent,
                'location_text' => 'Port of Spain, Trinidad',
                'country' => 'TT',
                'geo_eligibility' => GeoEligibility::Worldwide,
                'salary' => [900000, 1400000, 'TTD', 'monthly'], // $9,000–14,000 TTD/mo
                'skills' => [
                    ['php', true], ['mysql', true], ['html', true], ['css', true],
                    ['laravel', false], ['git', false],
                ],
            ],
            [
                'title' => '[DEMO] Customer Support Representative (Remote, Worldwide)',
                'company_name' => 'Demo Global SaaS Inc',
                'industry_id' => $bpo?->id,
                'work_arrangement' => WorkArrangement::RemoteInternational,
                'employment_type' => EmploymentType::Contract,
                'location_text' => 'Remote — Worldwide',
                'country' => 'US',
                'geo_eligibility' => GeoEligibility::Worldwide,
                'required_overlap_hours' => 4,
                'salary' => [120000, 180000, 'USD', 'monthly'], // $1,200–1,800 USD/mo
                'skills' => [
                    ['customer-service', true], ['ticketing-systems', true],
                    ['live-chat-support', false], ['crm-software', false],
                ],
            ],
            [
                'title' => '[DEMO] Accounts Clerk',
                'company_name' => 'Demo Finance Co',
                'industry_id' => $finance?->id,
                'work_arrangement' => WorkArrangement::OnPremises,
                'employment_type' => EmploymentType::Permanent,
                'location_text' => 'San Fernando, Trinidad',
                'country' => 'TT',
                'geo_eligibility' => GeoEligibility::Worldwide,
                'salary' => [600000, 850000, 'TTD', 'monthly'],
                'skills' => [
                    ['bookkeeping', true], ['microsoft-excel', true],
                    ['accounts-payable', false], ['quickbooks', false],
                ],
            ],
            [
                'title' => '[DEMO] Senior React Developer (US-only — ineligibility example)',
                'company_name' => 'Demo US Startup',
                'industry_id' => $ict?->id,
                'work_arrangement' => WorkArrangement::RemoteInternational,
                'employment_type' => EmploymentType::Permanent,
                'location_text' => 'Remote — US applicants only',
                'country' => 'US',
                'geo_eligibility' => GeoEligibility::CountryRestricted,
                'is_open_to_caribbean' => false,
                'salary' => [1000000, 1500000, 'USD', 'yearly'],
                'skills' => [
                    ['react', true], ['typescript', true], ['node-js', false],
                ],
            ],
        ];

        foreach ($jobs as $definition) {
            $skills = $definition['skills'];
            [$min, $max, $currency, $period] = $definition['salary'];
            unset($definition['skills'], $definition['salary']);

            $listing = JobListing::query()->updateOrCreate(
                ['source' => 'demo', 'source_job_id' => md5($definition['title'])],
                [
                    ...$definition,
                    'salary_min_cents' => $min,
                    'salary_max_cents' => $max,
                    'salary_currency' => $currency,
                    'salary_period' => $period,
                    'description' => 'Demo listing for local development. Not a real job.',
                    'apply_url' => 'https://example.com/demo',
                    'posted_at' => now()->subDays(3),
                    'is_active' => true,
                ],
            );

            $sync = [];
            foreach ($skills as [$slug, $required]) {
                if ($skill = Skill::query()->where('slug', $slug)->first()) {
                    $sync[$skill->id] = ['is_required' => $required, 'weight' => 1];
                }
            }
            $listing->skills()->sync($sync);
        }
    }
}
