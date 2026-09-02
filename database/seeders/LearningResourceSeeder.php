<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearningResourceSeeder extends Seeder
{
    /**
     * Recommendation catalog.
     *
     * Per docs/SPEC.md guardrails:
     *  - Local T&T entries seed ONLY the institution, its general offering
     *    area, and its official URL. Course names, prices, and durations are
     *    deliberately NULL for an admin to fill in; the UI must show
     *    "Contact provider for current course listing".
     *  - International entries likewise carry no invented prices; cost_note
     *    holds any free-text guidance.
     *
     * URLs verified against the live sites at seed-authoring time; re-verify
     * periodically (institutions rebrand — e.g. UWI Open Campus is now
     * marketed as UWI Global Campus).
     */
    public function run(): void
    {
        $local = [
            // [provider, offering area (title), url, delivery_mode, credential_type, notes]
            ['UWI St. Augustine', 'Undergraduate & postgraduate degrees; continuing education', 'https://sta.uwi.edu', 'blended', 'degree', null],
            ['UWI Global Campus (formerly Open Campus)', 'Online degrees, diplomas & professional development', 'https://global.uwi.edu', 'online', 'degree', 'Online arm of UWI; formerly UWI Open Campus.'],
            ['COSTAATT', 'Associate & bachelor degrees; technical and vocational programmes', 'https://costaatt.edu.tt', 'blended', 'degree', null],
            ['UWI-ROYTEC', 'Business, IT & professional development programmes', 'https://www.roytec.edu', 'blended', 'diploma', null],
            ['SBCS Global Learning Institute', 'Professional certifications (ACCA, CompTIA, PMI), IT & business programmes', 'https://www.sbcs.edu.tt', 'blended', 'certificate', null],
            ['MIC Institute of Technology (MIC-IT)', 'Technical & vocational training: machining, welding, electrical, process operations', 'https://www.mic.co.tt', 'in_person', 'certificate', null],
            ['NESC Technical Institute', 'Craft & technician training for energy, manufacturing and construction sectors', 'https://nesc.edu.tt', 'in_person', 'certificate', null],
            ['Cipriani College of Labour & Co-operative Studies', 'Labour studies, OSH, human resource management, project management', 'https://cclcs.edu.tt', 'blended', 'diploma', null],
            ['YTEPP Limited', 'Vocational courses across 12 occupational areas; youth training & employment preparation', 'https://www.ytepp.edu.tt', 'in_person', 'certificate', null],
            ['NEDCO', 'Entrepreneurship training & small-business development support', 'https://nedco.gov.tt', 'blended', 'certificate', 'Entrepreneurship track rather than employment credential.'],
            ['ACCA (Trinidad & Tobago)', 'Chartered certified accountancy qualification', 'https://www.accaglobal.com', 'blended', 'professional', 'Widely pursued in T&T; exams sittable locally.'],
            ['CIMA', 'Chartered management accountancy qualification', 'https://www.aicpa-cima.com', 'online', 'professional', null],
            ['CMI', 'Chartered management & leadership qualifications', 'https://www.managers.org.uk', 'online', 'professional', 'Offered locally through partner institutions such as SBCS.'],
            ['PMI Southern Caribbean Chapter', 'Project management certifications (PMP, CAPM) & local chapter events', 'https://www.pmi.org', 'blended', 'professional', null],
        ];

        $international = [
            ['Coursera', 'University-backed online courses, specializations & professional certificates', 'https://www.coursera.org', 'online', 'certificate', 'Financial aid available on many courses.'],
            ['edX', 'University-backed online courses & MicroMasters programmes', 'https://www.edx.org', 'online', 'certificate', 'Free audit track on many courses.'],
            ['Google Career Certificates', 'Entry-level professional certificates: IT support, data analytics, project management, UX, cybersecurity', 'https://grow.google/certificates', 'online', 'certificate', null],
            ['AWS Training & Certification', 'Cloud computing certifications (Cloud Practitioner through Professional)', 'https://aws.amazon.com/certification', 'online', 'certificate', null],
            ['Microsoft Learn / Azure Certifications', 'Cloud, data & developer certifications', 'https://learn.microsoft.com/credentials', 'online', 'certificate', 'Learning paths are free; exams are paid.'],
            ['Google Cloud Certification', 'Google Cloud engineer & architect certifications', 'https://cloud.google.com/learn/certification', 'online', 'certificate', null],
            ['CompTIA', 'Vendor-neutral IT certifications: A+, Network+, Security+', 'https://www.comptia.org', 'online', 'certificate', null],
            ['Salesforce Trailhead', 'Free Salesforce ecosystem training & administrator/developer certifications', 'https://trailhead.salesforce.com', 'online', 'certificate', 'Learning is free; certification exams are paid.'],
            ['HubSpot Academy', 'Free marketing, sales & CRM certifications', 'https://academy.hubspot.com', 'online', 'certificate', 'Certifications are free.'],
            ['Meta Blueprint', 'Digital marketing certifications for Meta platforms', 'https://www.facebook.com/business/learn', 'online', 'certificate', null],
            ['freeCodeCamp', 'Free full-curriculum web development, data & machine learning certifications', 'https://www.freecodecamp.org', 'online', 'certificate', 'Entirely free.'],
            ['Scrimba', 'Interactive front-end development courses', 'https://scrimba.com', 'online', 'certificate', 'Free tier available.'],
        ];

        $now = now();
        $rows = [];

        foreach ($local as [$provider, $title, $url, $mode, $credential, $notes]) {
            $rows[] = $this->row($provider, $title, $url, $mode, $credential, $notes, 'local_tt', $now);
        }

        foreach ($international as [$provider, $title, $url, $mode, $credential, $notes]) {
            $rows[] = $this->row($provider, $title, $url, $mode, $credential, $notes, 'international_online', $now);
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('learning_resources')->upsert(
                $chunk,
                uniqueBy: ['slug'],
                update: ['title', 'url', 'delivery_mode', 'credential_type', 'notes', 'provider_type'],
            );
        }

        $this->attachSkills($now);
    }

    /**
     * Which taxonomy skills each provider's GENERAL OFFERING AREA develops.
     * This is the resource→skill map the Skills Gap Plan uses; it names no
     * specific course, price or duration. Entries are skill categories
     * and/or canonical skill names from SkillSeeder; unknown names are
     * skipped, never invented. Weight 2 = specialist provider for that
     * skill, 1 = generalist that offers it among many things.
     *
     * Requires SkillSeeder to have run first (DatabaseSeeder orders this).
     */
    private function attachSkills($now): void
    {
        $map = [
            // ── Local T&T ──────────────────────────────────────────
            'uwi-st-augustine' => [1, ['software-it', 'finance-accounting', 'professional-services', 'education', 'healthcare', 'agriculture', 'construction'], []],
            'uwi-global-campus-formerly-open-campus' => [1, ['software-it', 'finance-accounting', 'professional-services', 'education', 'soft-skills'], []],
            'costaatt' => [1, ['software-it', 'healthcare', 'finance-accounting', 'office-admin', 'hospitality-tourism', 'creative-media'], []],
            'uwi-roytec' => [1, ['finance-accounting', 'professional-services', 'office-admin', 'sales-marketing'], ['Project Management', 'Data Analysis', 'IT Support']],
            'sbcs-global-learning-institute' => [2, ['finance-accounting', 'software-it'], ['Project Management', 'Human Resources', 'Business Analysis']],
            'mic-institute-of-technology-mic-it' => [2, ['trades-energy'], []],
            'nesc-technical-institute' => [2, ['trades-energy'], ['AutoCAD', 'Blueprint Reading']],
            'cipriani-college-of-labour-co-operative-studies' => [2, [], ['Occupational Health & Safety', 'Human Resources', 'Project Management', 'Training & Development', 'Recruitment', 'Conflict Resolution', 'Negotiation']],
            'ytepp-limited' => [1, ['hospitality-tourism', 'office-admin', 'agriculture'], ['Welding', 'Carpentry', 'Plumbing', 'Masonry', 'Automotive Repair', 'HVAC', 'Caregiving', 'Data Entry', 'Customer Service']],
            'nedco' => [1, [], ['Budgeting', 'Sales', 'Market Research', 'Brand Management', 'Digital Marketing']],
            'acca-trinidad-tobago' => [2, ['finance-accounting'], []],
            'cima' => [2, [], ['Financial Analysis', 'Budgeting', 'Financial Reporting', 'Risk Management', 'Treasury Management', 'Auditing']],
            'cmi' => [1, [], ['Leadership', 'Management Consulting', 'Human Resources', 'Training & Development', 'Negotiation', 'Project Management']],
            'pmi-southern-caribbean-chapter' => [2, [], ['Project Management', 'Agile Methodologies', 'Project Tracking Tools', 'Risk Management']],

            // ── International / online ─────────────────────────────
            'coursera' => [1, ['software-it', 'finance-accounting', 'sales-marketing', 'soft-skills', 'professional-services', 'remote-work', 'education'], ['Medical Billing & Coding', 'Supply Chain Management']],
            'edx' => [1, ['software-it', 'finance-accounting', 'professional-services', 'soft-skills'], []],
            'google-career-certificates' => [2, [], ['IT Support', 'Data Analysis', 'SQL', 'Project Management', 'Agile Methodologies', 'UI/UX Design', 'Cybersecurity', 'Digital Marketing', 'Email Marketing', 'Search Engine Optimization', 'Microsoft Excel']],
            'aws-training-certification' => [2, [], ['Amazon Web Services', 'DevOps', 'Docker', 'Linux', 'Cybersecurity']],
            'microsoft-learn-azure-certifications' => [2, [], ['Microsoft Azure', 'Microsoft 365 Administration', 'Active Directory', 'Power BI', '.NET', 'SQL', 'Data Analysis', 'IT Support']],
            'google-cloud-certification' => [2, [], ['Google Cloud Platform', 'DevOps', 'Docker', 'Machine Learning', 'Data Analysis']],
            'comptia' => [2, [], ['IT Support', 'Computer Networking', 'Cybersecurity', 'Linux', 'Active Directory']],
            'salesforce-trailhead' => [2, [], ['Salesforce', 'CRM Software']],
            'hubspot-academy' => [2, [], ['Digital Marketing', 'Email Marketing', 'CRM Software', 'Content Writing', 'Sales', 'Social Media Management', 'Search Engine Optimization']],
            'meta-blueprint' => [2, [], ['Digital Marketing', 'Paid Advertising', 'Social Media Management', 'Brand Management']],
            'freecodecamp' => [2, [], ['HTML', 'CSS', 'JavaScript', 'React', 'Node.js', 'Python', 'SQL', 'Git', 'REST APIs', 'Data Analysis', 'Machine Learning', 'TypeScript']],
            'scrimba' => [2, [], ['HTML', 'CSS', 'JavaScript', 'React', 'TypeScript', 'UI/UX Design', 'Git']],
        ];

        $resourceIds = DB::table('learning_resources')->pluck('id', 'slug');
        $skills = DB::table('skills')->get(['id', 'name', 'category']);
        $byName = $skills->keyBy(fn ($s) => mb_strtolower($s->name));
        $byCategory = $skills->groupBy('category');

        $rows = [];
        foreach ($map as $slug => [$weight, $categories, $names]) {
            $resourceId = $resourceIds[$slug] ?? null;
            if ($resourceId === null) {
                continue;
            }

            $skillIds = [];
            foreach ($categories as $category) {
                foreach ($byCategory[$category] ?? [] as $skill) {
                    $skillIds[$skill->id] = true;
                }
            }
            foreach ($names as $name) {
                if ($skill = $byName[mb_strtolower($name)] ?? null) {
                    $skillIds[$skill->id] = true;
                }
            }

            foreach (array_keys($skillIds) as $skillId) {
                $rows[] = [
                    'learning_resource_id' => $resourceId,
                    'skill_id' => $skillId,
                    'impact_weight' => $weight,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('learning_resource_skill')->upsert(
                $chunk,
                uniqueBy: ['learning_resource_id', 'skill_id'],
                update: ['impact_weight'],
            );
        }
    }

    private function row(
        string $provider,
        string $title,
        string $url,
        string $mode,
        string $credential,
        ?string $notes,
        string $providerType,
        $now,
    ): array {
        return [
            'title'           => $title,
            'provider'        => $provider,
            'slug'            => Str::slug($provider),
            'provider_type'   => $providerType,
            'delivery_mode'   => $mode,
            'url'             => $url,
            // Costs & durations intentionally NULL — admin fills them in;
            // UI shows "Contact provider for current course listing".
            'cost_min_cents'  => null,
            'cost_max_cents'  => null,
            'currency'        => null,
            'cost_note'       => null,
            'duration_weeks'  => null,
            'credential_type' => $credential,
            'notes'           => $notes,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];
    }
}
