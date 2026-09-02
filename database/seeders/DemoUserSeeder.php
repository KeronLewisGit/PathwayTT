<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\DemoOnly;
use Illuminate\Database\Seeder;

/**
 * Demo accounts (local / APP_DEMO_DATA only). Password for all: "password".
 *
 *  admin@pathwaytt.test   — Filament admin
 *  demo@pathwaytt.test    — Aaliyah Mohammed: office / customer-service
 *                           profile, filled in and active (see DemoActivitySeeder)
 *  marcus@pathwaytt.test  — Marcus Charles: welder / pipefitter, energy sector
 *  tester1..3@pathwaytt.test — empty, verified accounts so testers can
 *                           experience onboarding from scratch
 */
class DemoUserSeeder extends Seeder
{
    use DemoOnly;

    public const DEMO_EMAILS = [
        'demo@pathwaytt.test', 'marcus@pathwaytt.test',
        'tester1@pathwaytt.test', 'tester2@pathwaytt.test', 'tester3@pathwaytt.test',
    ];

    public function run(): void
    {
        if (! self::demoAllowed()) {
            return;
        }

        // Local admin for the Filament panel (production admins are created
        // per docs/DEPLOYMENT.md). is_admin is deliberately not mass-assignable.
        User::query()->firstOrCreate(
            ['email' => 'admin@pathwaytt.test'],
            ['name' => 'Admin', 'password' => 'password', 'email_verified_at' => now()],
        )->forceFill(['is_admin' => true])->save();

        foreach ([
            'demo@pathwaytt.test' => 'Aaliyah Mohammed',
            'marcus@pathwaytt.test' => 'Marcus Charles',
            'tester1@pathwaytt.test' => 'Tester One',
            'tester2@pathwaytt.test' => 'Tester Two',
            'tester3@pathwaytt.test' => 'Tester Three',
        ] as $email => $name) {
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'email_verified_at' => now()],
            );

            if ($user->name !== $name) {
                $user->forceFill(['name' => $name])->save();
            }
        }
    }
}
