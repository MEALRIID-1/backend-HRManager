<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FichePaieFactory extends Factory
{
    protected $model = FichePaie::class;

    public function definition(): array
    {
        $mois = fake()->numberBetween(1, 12);
        $annee = fake()->numberBetween(2023, 2025);
        $salaireBrut = fake()->randomFloat(2, 2000, 5000);
        
        return [
            'user_id' => User::factory(),
            'periode' => sprintf('%02d/%d', $mois, $annee),
            'date_emission' => fake()->date(),
            'salaire_brut' => $salaireBrut,
            'salaire_net' => $salaireBrut * 0.75, // estimation
            'heures_travaillees' => fake()->randomFloat(2, 140, 180),
            'heures_supplementaires' => fake()->optional()->randomFloat(2, 0, 20),
            'montant_heures_sup' => fake()->optional()->randomFloat(2, 0, 500),
            'prime_anciennete' => fake()->optional()->randomFloat(2, 0, 200),
            'prime_productivite' => fake()->optional()->randomFloat(2, 0, 300),
            'prime_autres' => fake()->optional()->randomFloat(2, 0, 150),
            'total_cotisations' => $salaireBrut * 0.25, // estimation
            'total_retenues' => $salaireBrut * 0.05, // estimation
            'statut' => fake()->randomElement(['brouillon', 'validee', 'payee']),
        ];
    }

    public function brouillon(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'brouillon',
        ]);
    }

    public function finalisee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'finalisee',
        ]);
    }

    public function payee(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'payee',
        ]);
    }
}
