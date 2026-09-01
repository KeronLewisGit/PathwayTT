<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPreferenceFactory extends Factory
{
    protected $model = JobPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'industry_id' => Industry::factory(),
            'work_arrangements' => [fake()->randomElement(WorkArrangement::cases())->value],
            'employment_types' => [fake()->randomElement(EmploymentType::cases())->value],
            'seniority' => fake()->randomElement(['entry', 'mid', 'senior']),
            'min_salary_cents' => fake()->numberBetween(4000, 15000) * 100,
            'min_salary_currency' => fake()->randomElement(['TTD', 'USD']),
            'min_salary_period' => 'monthly',
        ];
    }
}
