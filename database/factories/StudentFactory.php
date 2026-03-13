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
            'customer_number' => 'MS-' . fake()->unique()->numberBetween(1000, 9999),
            'name' => fake('de_DE')->firstName() . ' ' . fake('de_DE')->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_2' => fake()->boolean(30) ? fake()->unique()->safeEmail() : null,
            'phone' => fake('de_DE')->phoneNumber(),
            'active' => true,
        ];
    }
}
