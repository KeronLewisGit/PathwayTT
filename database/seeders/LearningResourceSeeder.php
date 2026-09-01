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
