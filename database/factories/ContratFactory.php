<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContratFactory extends Factory
{
    protected $model = Contrat::class;

    public function definition(): array
    {
        $types = ['cdi', 'cdd', 'stage', 'alternance', 'freelance'];
        $statuts = ['actif', 'termine', 'suspendu'];

        $dateDebut = fake()->dateTimeBetween('-2 years', 'now');
        $dateFin = fake()->optional(0.3)->dateTimeBetween($dateDebut, '+1 year');

        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement($types),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'salaire_brut' => fake()->randomFloat(2, 2000, 8000),
            'statut' => fake()->randomElement($statuts),
        ];
    }

    public function actif(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => 'actif',
        ]);
    }

    public function cdi(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'cdi',
            'date_fin' => null,
        ]);
    }
}
