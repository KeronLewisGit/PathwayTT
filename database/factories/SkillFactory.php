<?php

namespace Database\Factories;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SkillFactory extends Factory
{
    protected $model = Skill::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'category' => fake()->randomElement(['software-it', 'office-admin', 'finance-accounting']), // non-generic: generic categories are not match evidence
            'aliases' => [],
        ];
    }

    public function withAliases(array $aliases): static
    {
        return $this->state(fn () => ['aliases' => $aliases]);
    }
}
