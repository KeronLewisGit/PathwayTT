<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\GeoEligibility;
use App\Enums\WorkArrangement;
use App\Models\Industry;
use App\Models\JobListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobListingFactory extends Factory
{
    protected $model = JobListing::class;

    public function definition(): array
    {
        return [
            'source' => 'factory',
            'source_job_id' => fake()->unique()->uuid(),
            'title' => fake()->jobTitle(),
            'company_name' => fake()->company(),
            'industry_id' => Industry::factory(),
            'work_arrangement' => fake()->randomElement(WorkArrangement::cases()),
            'employment_type' => fake()->randomElement(EmploymentType::cases()),
            'location_text' => fake()->city(),
            'country' => fake()->randomElement(['TT', 'US', 'GB', 'CA']),
            'geo_eligibility' => GeoEligibility::Worldwide,
            'seniority' => fake()->randomElement(['entry', 'mid', 'senior']),
            'salary_min_cents' => fake()->numberBetween(4000, 8000) * 100,
            'salary_max_cents' => fake()->numberBetween(8001, 20000) * 100,
            'salary_currency' => fake()->randomElement(['TTD', 'USD']),
            'salary_period' => 'monthly',
            'description' => fake()->paragraphs(3, true),
            'apply_url' => fake()->url(),
            'posted_at' => fake()->dateTimeBetween('-30 days'),
            'is_active' => true,
        ];
    }

    public function usOnly(): static
    {
        return $this->state(fn () => [
            'country' => 'US',
            'geo_eligibility' => GeoEligibility::CountryRestricted,
            'is_open_to_caribbean' => false,
            'work_arrangement' => WorkArrangement::RemoteInternational,
        ]);
    }

    public function remoteWorldwide(): static
    {
        return $this->state(fn () => [
            'geo_eligibility' => GeoEligibility::Worldwide,
            'work_arrangement' => WorkArrangement::RemoteInternational,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closes_at' => now()->subDay()]);
    }
}
