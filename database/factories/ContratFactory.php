<?php

namespace Database\Factories;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrat>
 */
class ContratFactory extends Factory
{
    protected $model = Contrat::class;

    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('-2 years', '+1 month');
        $type = fake()->randomElement([
            Contrat::TYPE_CDI,
            Contrat::TYPE_CDD,
            Contrat::TYPE_STAGE,
            Contrat::TYPE_ALTERNANCE,
        ]);

        $salaire = match ($type) {
            Contrat::TYPE_STAGE => fake()->randomFloat(2, 600, 1500),
            Contrat::TYPE_ALTERNANCE => fake()->randomFloat(2, 800, 1800),
            Contrat::TYPE_CDD => fake()->randomFloat(2, 1800, 4500),
            Contrat::TYPE_CDI => fake()->randomFloat(2, 2500, 8000),
            default => fake()->randomFloat(2, 2000, 6000),
        };

        $dureeMois = fake()->numberBetween(6, 24);

        return [
            'employe_id' => User::factory(),
            'type' => $type,
            'date_debut' => $dateDebut,
            'date_fin' => $type === Contrat::TYPE_CDI ? null : (clone $dateDebut)->modify("+{$dureeMois} months"),
            'salaire' => $salaire,
            'etat' => Contrat::ETAT_EN_COURS,
            'fonction' => fake()->jobTitle(),
            'duree_periode_essai_jours' => fake()->numberBetween(30, 90),
            'created_at' => $dateDebut,
            'updated_at' => now(),
        ];
    }

    public function cdi(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contrat::TYPE_CDI,
            'date_fin' => null,
            'salaire' => fake()->randomFloat(2, 2500, 8000),
        ]);
    }

    public function cdd(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contrat::TYPE_CDD,
            'date_fin' => fake()->dateTimeBetween('+3 months', '+2 years'),
            'salaire' => fake()->randomFloat(2, 1800, 4500),
        ]);
    }

    public function stage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contrat::TYPE_STAGE,
            'date_fin' => fake()->dateTimeBetween('+2 months', '+6 months'),
            'salaire' => fake()->randomFloat(2, 600, 1500),
        ]);
    }

    public function alternance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contrat::TYPE_ALTERNANCE,
            'date_fin' => fake()->dateTimeBetween('+12 months', '+36 months'),
            'salaire' => fake()->randomFloat(2, 800, 1800),
        ]);
    }

    public function actif(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Contrat::ETAT_EN_COURS,
        ]);
    }

    public function termine(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Contrat::ETAT_TERMINE,
            'date_fin' => fake()->dateTimeBetween('-6 months', '-1 day'),
        ]);
    }

    public function enAttente(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Contrat::ETAT_BROUILLON,
            'date_debut' => fake()->dateTimeBetween('+1 week', '+2 months'),
        ]);
    }

    public function pourEmploye(int $employeId): static
    {
        return $this->state(fn (array $attributes) => [
            'employe_id' => $employeId,
        ]);
    }
}
