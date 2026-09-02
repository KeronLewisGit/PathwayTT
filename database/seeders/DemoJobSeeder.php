<?php

namespace Database\Seeders;

use App\Models\Industry;
use App\Models\JobListing;
use App\Models\Skill;
use Database\Seeders\Concerns\DemoOnly;
use Illuminate\Database\Seeder;

/**
 * DEMO DATA — local / APP_DEMO_DATA only.
 *
 * These are NOT real job listings. Every record carries source = "demo",
 * a [DEMO] title prefix, a fictional "Demo …" employer and an example.com
 * apply link, so they can never be mistaken for, or mixed into, real
 * sourced jobs. They exist so testers see a realistic spread of LOCAL
 * Trinidad & Tobago roles (the remote boards supply the international ones).
 *
 * Salaries are illustrative TTD ranges, stored as integer cents.
 */
class DemoJobSeeder extends Seeder
{
    use DemoOnly;

    public function run(): void
    {
        if (! self::demoAllowed()) {
            return;
        }

        $industries = Industry::query()->pluck('id', 'slug');
        $skills = Skill::query()->pluck('id', 'name');

        foreach ($this->listings() as $definition) {
            $skillMap = $definition['skills'];
            [$min, $max, $currency, $period] = $definition['salary'];
            $industrySlug = $definition['industry'];
            $daysAgo = $definition['days_ago'] ?? 3;
            $description = $definition['description'] ?? 'Demo listing for evaluation purposes. Not a real job.';
            // Seeders run unguarded, so strip the helper keys that aren't columns.
            unset($definition['skills'], $definition['salary'], $definition['industry'], $definition['days_ago'], $definition['description']);

            $listing = JobListing::query()->updateOrCreate(
                ['source' => 'demo', 'source_job_id' => md5($definition['title'])],
                [
                    ...$definition,
                    'title' => '[DEMO] '.$definition['title'],
                    'industry_id' => $industries[$industrySlug] ?? null,
                    'salary_min_cents' => $min,
                    'salary_max_cents' => $max,
                    'salary_currency' => $currency,
                    'salary_period' => $period,
                    'description' => $description."\n\nThis is a demo listing created for testing PathwayTT; the employer and role are fictional.",
                    'apply_url' => 'https://example.com/demo-jobs/'.md5($definition['title']),
                    'posted_at' => now()->subDays($daysAgo),
                    'closes_at' => now()->addDays(30),
                    'is_active' => true,
                ],
            );

            $sync = [];
            foreach ($skillMap as $name => $required) {
                if (isset($skills[$name])) {
                    $sync[$skills[$name]] = ['is_required' => $required, 'weight' => 1];
                }
            }
            $listing->skills()->sync($sync);
        }
    }

    /** @return list<array<string, mixed>> */
    private function listings(): array
    {
        $local = fn (string $location) => ['work_arrangement' => 'on_premises', 'location_text' => $location, 'country' => 'TT', 'geo_eligibility' => 'worldwide'];
        $hybrid = fn (string $location) => ['work_arrangement' => 'hybrid_local', 'location_text' => $location, 'country' => 'TT', 'geo_eligibility' => 'worldwide'];

        return [
            // ── Office, customer service, finance ────────────────────
            [
                'title' => 'Customer Service Representative', 'company_name' => 'Demo Telecom Ltd', 'industry' => 'bpo-contact-centre',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'bir'],
                'salary' => [550000, 700000, 'TTD', 'monthly'], 'days_ago' => 2,
                'description' => "Handle inbound customer queries by phone, chat and email for a national telecoms provider. Shift work including weekends.\n\nRequirements: 5 CSEC passes including English and Mathematics; one year in a customer-facing role; comfortable with CRM systems.",
                'skills' => ['Customer Service' => true, 'Call Handling' => true, 'CRM Software' => false, 'Live Chat Support' => false, 'Communication' => false],
            ],
            [
                'title' => 'Accounts Payable Clerk', 'company_name' => 'Demo Manufacturing Ltd', 'industry' => 'manufacturing',
                ...$local('Chaguanas, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 2, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'bir'],
                'salary' => [600000, 800000, 'TTD', 'monthly'], 'days_ago' => 4,
                'description' => "Process supplier invoices, reconcile statements and prepare payment runs for a food manufacturer.\n\nRequirements: CSEC Principles of Accounts; two years' accounts experience; strong Excel.",
                'skills' => ['Accounts Payable' => true, 'Microsoft Excel' => true, 'QuickBooks' => false, 'Bookkeeping' => false, 'Attention to Detail' => false],
            ],
            [
                'title' => 'Call Centre Agent (Evening Shift, US Clients)', 'company_name' => 'Demo BPO Services', 'industry' => 'bpo-contact-centre',
                ...$hybrid('Chaguanas, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 0, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'police_certificate'],
                'salary' => [500000, 650000, 'TTD', 'monthly'], 'days_ago' => 1,
                'description' => "Support North American customers from our Chaguanas centre, 2pm–11pm AST. Two days a week from home after probation.\n\nRequirements: excellent spoken English; CSEC English; police certificate of character.",
                'skills' => ['Customer Service' => true, 'Call Handling' => true, 'Ticketing Systems' => false, 'CRM Software' => false],
            ],
            [
                'title' => 'Junior Accountant', 'company_name' => 'Demo Audit & Co', 'industry' => 'professional-services-accountinglegalconsulting',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'mid',
                'min_years_experience' => 2, 'min_education_level' => 'bsc', 'required_credentials' => ['nis', 'bir'],
                'salary' => [800000, 1100000, 'TTD', 'monthly'], 'days_ago' => 6,
                'description' => "Prepare monthly management accounts and assist with statutory audits for a mid-sized firm.\n\nRequirements: BSc Accounting or ACCA Level 2; two years' experience; IFRS knowledge an asset.",
                'skills' => ['Financial Reporting' => true, 'Bookkeeping' => true, 'IFRS' => false, 'Auditing' => false, 'Microsoft Excel' => false],
            ],
            [
                'title' => 'HR Assistant', 'company_name' => 'Demo Insurance Ltd', 'industry' => 'financial-services-insurance',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 2, 'min_education_level' => 'diploma', 'required_credentials' => ['nis'],
                'salary' => [700000, 900000, 'TTD', 'monthly'], 'days_ago' => 8,
                'description' => "Coordinate recruitment, onboarding and HR records for a 200-person insurer.\n\nRequirements: diploma in HR or business; two years in an HR or administrative role.",
                'skills' => ['Recruitment' => true, 'Human Resources' => true, 'Microsoft Excel' => false, 'Records Management' => false],
            ],
            [
                'title' => 'Office Administrator', 'company_name' => 'Demo Shipping Agency', 'industry' => 'logistics-shipping',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'bir'],
                'salary' => [500000, 650000, 'TTD', 'monthly'], 'days_ago' => 5,
                'skills' => ['Office Administration' => true, 'Microsoft Word' => true, 'Microsoft Excel' => true, 'Shipping Documentation' => false, 'Data Entry' => false],
            ],

            // ── Energy & trades ───────────────────────────────────────
            [
                'title' => 'Welder / Fabricator', 'company_name' => 'Demo Fabrication Ltd', 'industry' => 'energy-petrochemicals',
                ...$local('Point Fortin, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'mid',
                'min_years_experience' => 3, 'min_education_level' => 'certificate', 'required_credentials' => ['nis'],
                'salary' => [900000, 1400000, 'TTD', 'monthly'], 'days_ago' => 2,
                'description' => "Fabricate and weld structural steel and piping for onshore energy clients. 6G-certified welders preferred.\n\nRequirements: three years' welding; ability to read fabrication drawings.",
                'skills' => ['Welding' => true, 'Blueprint Reading' => true, 'Pipefitting' => false, 'Rigging' => false, 'Occupational Health & Safety' => false],
            ],
            [
                'title' => 'Pipefitter — Plant Shutdown (3-month contract)', 'company_name' => 'Demo Energy Services', 'industry' => 'energy-petrochemicals',
                ...$local('Point Lisas, Trinidad'), 'employment_type' => 'contract', 'seniority' => 'senior',
                'min_years_experience' => 4, 'min_education_level' => 'certificate', 'required_credentials' => ['nis', 'police_certificate'],
                'salary' => [1200000, 1600000, 'TTD', 'monthly'], 'days_ago' => 1,
                'description' => "Turnaround work at a Point Lisas plant: install and test process piping under a permit-to-work system.\n\nRequirements: four years' industrial pipefitting; police certificate of character for site access.",
                'skills' => ['Pipefitting' => true, 'Permit-to-Work Systems' => true, 'Scaffolding' => false, 'Occupational Health & Safety' => false, 'Rigging' => false],
            ],
            [
                'title' => 'Process Operator', 'company_name' => 'Demo Petrochemicals Ltd', 'industry' => 'energy-petrochemicals',
                ...$local('Point Lisas, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'mid',
                'min_years_experience' => 3, 'min_education_level' => 'diploma', 'required_credentials' => ['nis', 'police_certificate'],
                'salary' => [1500000, 2200000, 'TTD', 'monthly'], 'days_ago' => 9,
                'skills' => ['Process Plant Operations' => true, 'Instrumentation' => false, 'Industrial Electrical' => false, 'Permit-to-Work Systems' => false],
            ],
            [
                'title' => 'Forklift Operator', 'company_name' => 'Demo Logistics Co', 'industry' => 'logistics-shipping',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => null, 'required_credentials' => ['nis', 'drivers_permit'],
                'salary' => [450000, 600000, 'TTD', 'monthly'], 'days_ago' => 3,
                'skills' => ['Forklift Operation' => true, 'Warehouse Operations' => false, 'Inventory Management' => false],
            ],
            [
                'title' => 'Delivery Driver', 'company_name' => 'Demo Distribution Ltd', 'industry' => 'distribution-retail',
                ...$local('San Juan, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => null, 'required_credentials' => ['drivers_permit', 'police_certificate'],
                'salary' => [450000, 550000, 'TTD', 'monthly'], 'days_ago' => 7,
                'skills' => ['Driving' => true, 'Route Planning' => false, 'Customer Service' => false],
            ],

            // ── Retail, hospitality, care, education, creative ────────
            [
                'title' => 'Retail Sales Associate', 'company_name' => 'Demo Retail Group', 'industry' => 'distribution-retail',
                ...$local('San Fernando, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 0, 'min_education_level' => 'csec', 'required_credentials' => ['nis'],
                'salary' => [400000, 500000, 'TTD', 'monthly'], 'days_ago' => 2,
                'skills' => ['Sales' => true, 'Customer Service' => true, 'Point of Sale Systems' => false, 'Merchandising' => false],
            ],
            [
                'title' => 'Front Desk Agent', 'company_name' => 'Demo Beach Resort', 'industry' => 'tourism-hospitality',
                ...$local('Crown Point, Tobago'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'police_certificate'],
                'salary' => [500000, 650000, 'TTD', 'monthly'], 'days_ago' => 4,
                'description' => 'Welcome guests, manage check-in/out and reservations at a 60-room Tobago resort. Staff accommodation available for Trinidad applicants willing to relocate.',
                'skills' => ['Hotel Front Desk Operations' => true, 'Customer Service' => true, 'Reservations Systems' => false, 'Communication' => false],
            ],
            [
                'title' => 'Nursing Assistant', 'company_name' => 'Demo Care Home', 'industry' => 'healthcare',
                ...$local('San Fernando, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 0, 'min_education_level' => 'csec', 'required_credentials' => ['nis', 'police_certificate'],
                'salary' => [450000, 600000, 'TTD', 'monthly'], 'days_ago' => 5,
                'skills' => ['Nursing Assistance' => true, 'Patient Care' => true, 'First Aid & CPR' => false, 'Caregiving' => false],
            ],
            [
                'title' => 'Primary School Teacher', 'company_name' => 'Demo Private School', 'industry' => 'education',
                ...$local('Arima, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'mid',
                'min_years_experience' => 1, 'min_education_level' => 'diploma', 'required_credentials' => ['police_certificate'],
                'salary' => [600000, 800000, 'TTD', 'monthly'], 'days_ago' => 10,
                'skills' => ['Lesson Planning' => true, 'Classroom Management' => true, 'Communication' => false],
            ],
            [
                'title' => 'Digital Marketing Coordinator', 'company_name' => 'Demo Creative Agency', 'industry' => 'creative-media',
                ...$hybrid('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'mid',
                'min_years_experience' => 2, 'min_education_level' => 'diploma', 'required_credentials' => ['nis'],
                'salary' => [700000, 950000, 'TTD', 'monthly'], 'days_ago' => 3,
                'skills' => ['Digital Marketing' => true, 'Social Media Management' => true, 'Graphic Design' => false, 'Adobe Photoshop' => false, 'Content Writing' => false],
            ],
            [
                'title' => 'Junior Web Developer', 'company_name' => 'Demo Local Software Ltd', 'industry' => 'ict-software',
                ...$local('Port of Spain, Trinidad'), 'employment_type' => 'permanent', 'seniority' => 'entry',
                'min_years_experience' => 1, 'min_education_level' => 'diploma', 'required_credentials' => ['nis'],
                'salary' => [900000, 1400000, 'TTD', 'monthly'], 'days_ago' => 3,
                'skills' => ['PHP' => true, 'MySQL' => true, 'HTML' => true, 'CSS' => true, 'Laravel' => false, 'Git' => false],
            ],

            // ── Remote / international (eligibility examples) ────────
            [
                'title' => 'Customer Support Representative (Remote, Worldwide)', 'company_name' => 'Demo Global SaaS Inc', 'industry' => 'bpo-contact-centre',
                'work_arrangement' => 'remote_international', 'location_text' => 'Remote — Worldwide', 'country' => 'US',
                'geo_eligibility' => 'worldwide', 'is_open_to_caribbean' => true, 'required_overlap_hours' => 4,
                'employment_type' => 'contract', 'seniority' => 'entry', 'min_years_experience' => 1, 'min_education_level' => null,
                'salary' => [120000, 180000, 'USD', 'monthly'], 'days_ago' => 2,
                'description' => "Support customers of a US software company by chat and email, 9am–5pm US Eastern (overlaps AST). Contractor role paid monthly in USD via Wise or Payoneer.",
                'skills' => ['Customer Service' => true, 'Ticketing Systems' => true, 'Live Chat Support' => false, 'CRM Software' => false, 'Asynchronous Communication' => false],
            ],
            [
                'title' => 'Remote Bookkeeper (LATAM & Caribbean)', 'company_name' => 'Demo Accounting Cloud Inc', 'industry' => 'financial-services-insurance',
                'work_arrangement' => 'remote_international', 'location_text' => 'Remote — LATAM & Caribbean', 'country' => 'US',
                'geo_eligibility' => 'region_restricted', 'is_open_to_caribbean' => true, 'required_overlap_hours' => 5,
                'employment_type' => 'contract', 'seniority' => 'mid', 'min_years_experience' => 2, 'min_education_level' => 'csec',
                'salary' => [90000, 140000, 'USD', 'monthly'], 'days_ago' => 4,
                'skills' => ['Bookkeeping' => true, 'QuickBooks' => true, 'Microsoft Excel' => false, 'Accounts Payable' => false],
            ],
            [
                'title' => 'Senior React Developer (US-only — ineligibility example)', 'company_name' => 'Demo US Startup', 'industry' => 'ict-software',
                'work_arrangement' => 'remote_international', 'location_text' => 'Remote — US applicants only', 'country' => 'US',
                'geo_eligibility' => 'country_restricted', 'is_open_to_caribbean' => false,
                'employment_type' => 'permanent', 'seniority' => 'senior', 'min_years_experience' => 5, 'min_education_level' => null,
                'salary' => [1000000, 1500000, 'USD', 'yearly'], 'days_ago' => 6,
                'skills' => ['React' => true, 'TypeScript' => true, 'Node.js' => false],
            ],
        ];
    }
}
