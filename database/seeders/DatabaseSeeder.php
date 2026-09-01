<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference data — always seeded, idempotent upserts.
        $this->call([
            IndustrySeeder::class,
            SkillSeeder::class,
            LearningResourceSeeder::class,
            SettingSeeder::class,
        ]);

        // Demo data — local development only, clearly labelled.
        if (app()->environment('local')) {
            $this->call([
                DemoUserSeeder::class,
                DemoJobSeeder::class,
            ]);
        }
    }
}
