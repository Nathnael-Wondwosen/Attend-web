<?php

namespace Database\Factories;

use App\Models\ClassModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassModelFactory extends Factory
{
    protected $model = ClassModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' Class',
            'grade' => $this->faker->numberBetween(1, 12),
            'section' => $this->faker->randomElement(['A', 'B', 'C']),
            'academic_year' => 2025,
            'capacity' => $this->faker->numberBetween(20, 35),
            'description' => $this->faker->sentence(),
        ];
    }
}