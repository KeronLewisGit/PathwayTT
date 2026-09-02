<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\DemoOnly;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use DemoOnly;

    public function run(): void
    {
        // Reference data — always seeded, idempotent upserts.
        $this->call([
            IndustrySeeder::class,
            SkillSeeder::class,
            LearningResourceSeeder::class,
            SettingSeeder::class,
        ]);

        // Demo data — local env or APP_DEMO_DATA=true only, clearly labelled.
        if (self::demoAllowed()) {
            $this->call([
                DemoUserSeeder::class,
                DemoJobSeeder::class,
                DemoActivitySeeder::class,
            ]);
        }
    }
}
