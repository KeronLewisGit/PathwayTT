<?php

namespace Database\Factories;

use App\Enums\ProviderType;
use App\Models\LearningResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LearningResourceFactory extends Factory
{
    protected $model = LearningResource::class;

    public function definition(): array
    {
        $provider = fake()->unique()->company();

        return [
            'title' => fake()->catchPhrase(),
            'provider' => $provider,
            'slug' => Str::slug($provider),
            'provider_type' => fake()->randomElement(ProviderType::cases()),
            'delivery_mode' => fake()->randomElement(['in_person', 'online', 'blended']),
            'url' => fake()->url(),
            'credential_type' => fake()->randomElement(['certificate', 'diploma', 'degree', 'badge']),
        ];
    }
}
