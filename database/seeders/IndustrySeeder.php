<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndustrySeeder extends Seeder
{
    /**
     * T&T-relevant sectors, per docs/SPEC.md.
     */
    public function run(): void
    {
        $industries = [
            'Energy & Petrochemicals',
            'Financial Services & Insurance',
            'ICT & Software',
            'Manufacturing',
            'Distribution & Retail',
            'Construction',
            'Agriculture & Agro-processing',
            'Tourism & Hospitality',
            'Healthcare',
            'Education',
            'Public Sector',
            'Creative & Media',
            'Logistics & Shipping',
            'BPO & Contact Centre',
            'Professional Services (Accounting/Legal/Consulting)',
        ];

        $now = now();

        DB::table('industries')->upsert(
            collect($industries)->map(fn (string $name) => [
                'name'       => $name,
                'slug'       => Str::slug($name),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
            uniqueBy: ['slug'],
            update: ['name'],
        );
    }
}
