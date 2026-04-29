<?php

namespace Database\Factories;

use App\Models\SoldeConge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoldeConge>
 */
class SoldeCongeFactory extends Factory
{
    protected $model = SoldeConge::class;

    public function definition(): array
    {
        $types = ['annuel', 'maladie', 'sans_solde', 'maternite', 'paternite', 'rtt'];

        return [
            'employe_id' => User::factory(),
            'type' => fake()->randomElement($types),
            'solde' => fake()->numberBetween(0, 30),
            'solde_initial' => fake()->numberBetween(25, 30),
            'annee' => fake()->numberBetween(2023, 2025),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function annuel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'annuel',
            'solde' => 25,
            'solde_initial' => 25,
        ]);
    }
}
