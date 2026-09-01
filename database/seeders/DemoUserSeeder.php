<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * Local-dev demo account. Guarded in DatabaseSeeder to local env only.
     */
    public function run(): void
    {
        // Local admin for the Filament panel (production admins are created
        // with `php artisan make:filament-user` + setting is_admin).
        User::query()->firstOrCreate(
            ['email' => 'admin@pathwaytt.test'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        )->forceFill(['is_admin' => true])->save(); // is_admin is deliberately not mass-assignable

        $user = User::query()->firstOrCreate(
            ['email' => 'demo@pathwaytt.test'],
            [
                'name' => 'Demo User',
                'password' => 'password', // hashed by the User model cast
                'email_verified_at' => now(),
            ],
        );

        $user->profile()->firstOrCreate([], [
            'full_name' => 'Demo User',
            'region' => 'Port of Spain',
            'years_experience' => 3,
            'highest_education_level' => 'cape',
            'has_nis' => true,
            'has_bir' => true,
            'willing_to_relocate' => true,
        ]);
    }
}
