<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'qualification' => $this->faker->randomElement(['B.Ed', 'M.Ed', 'PhD']),
            'experience_years' => $this->faker->numberBetween(1, 20),
            'is_active' => true,
        ];
    }
}