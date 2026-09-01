<?php

namespace Database\Factories;

use App\Enums\QualificationType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'region' => fake()->randomElement([
                'Port of Spain', 'San Fernando', 'Chaguanas', 'Arima',
                'Point Fortin', 'Couva', 'Sangre Grande', 'Tobago',
            ]),
            'years_experience' => fake()->numberBetween(0, 20),
            'highest_education_level' => fake()->randomElement(QualificationType::cases()),
            'has_nis' => fake()->boolean(80),
            'has_bir' => fake()->boolean(70),
            'has_drivers_permit' => fake()->boolean(50),
            'has_police_certificate' => fake()->boolean(30),
            'willing_to_relocate' => fake()->boolean(40),
            'availability_date' => fake()->optional()->dateTimeBetween('now', '+2 months'),
        ];
    }
}
