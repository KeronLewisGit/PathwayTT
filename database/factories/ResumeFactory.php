<?php

namespace Database\Factories;

use App\Enums\ParseStatus;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResumeFactory extends Factory
{
    protected $model = Resume::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_filename' => fake()->word().'.pdf',
            'path' => 'resumes/1/'.Str::uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 4_000_000),
            'parse_status' => ParseStatus::Pending,
        ];
    }
}
