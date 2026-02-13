<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'christian_name' => $this->faker->firstName(),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'birth_date' => $this->faker->date('Y-m-d', '-10 years'),
            'current_grade' => $this->faker->numberBetween(1, 12),
        ];
    }
}