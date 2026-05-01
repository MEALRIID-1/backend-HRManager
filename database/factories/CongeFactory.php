<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CongeFactory extends Factory
{
    protected $model = Conge::class;

    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('now', '+1 month');
        $nombreJours = fake()->numberBetween(1, 10);
        $dateFin = (clone $dateDebut)->modify('+' . $nombreJours . ' days');

        $types = ['conge_payes', 'maladie', 'sans_solde', 'formation', 'maternite', 'paternite', 'famille'];
        $statuts = ['en_attente', 'partiellement_valide', 'approuve', 'refuse'];

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement($types),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'nombre_jours' => $nombreJours,
            'statut' => fake()->randomElement($statuts),
            'niveau_validation' => 0,
            'commentaire' => fake()->optional()->paragraph(),
        ];
    }

    public function enAttente(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'en_attente',
        ]);
    }

    public function approuve(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'approuve',
        ]);
    }

    public function refuse(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'refuse',
        ]);
    }
}
