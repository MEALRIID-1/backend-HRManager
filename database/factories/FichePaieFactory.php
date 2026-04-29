<?php

namespace Database\Factories;

use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FichePaie>
 */
class FichePaieFactory extends Factory
{
    protected $model = FichePaie::class;

    public function definition(): array
    {
        $mois = fake()->numberBetween(1, 12);
        $annee = fake()->numberBetween(2023, 2025);
        
        $montant = fake()->randomFloat(2, 2500, 6500);

        return [
            'employe_id' => User::factory(),
            'mois' => $mois,
            'annee' => $annee,
            'montant' => $montant,
            'etat' => fake()->randomElement(['brouillon', 'genere', 'valide', 'envoye']),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function genere(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => 'genere',
        ]);
    }

    public function valide(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => 'valide',
        ]);
    }

    public function envoye(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => 'envoye',
        ]);
    }

    public function pourPeriode(int $mois, int $annee): static
    {
        return $this->state(fn (array $attributes) => [
            'mois' => $mois,
            'annee' => $annee,
        ]);
    }

    public function pourEmploye(int $employeId): static
    {
        return $this->state(fn (array $attributes) => [
            'employe_id' => $employeId,
        ]);
    }
}
