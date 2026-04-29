<?php

namespace Database\Factories;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conge>
 */
class CongeFactory extends Factory
{
    protected $model = Conge::class;

    public function definition(): array
    {
        $dateDebut = fake()->dateTimeBetween('-6 months', '+6 months');
        $duree = fake()->numberBetween(1, 25);
        $dateFin = (clone $dateDebut)->modify('+'.($duree - 1).' days');

        $types = [
            Conge::TYPE_CONGE_PAYE,
            Conge::TYPE_CONGE_PAYE,
            Conge::TYPE_CONGE_PAYE,
            Conge::TYPE_MALADIE,
            Conge::TYPE_CONGE_SANS_SOLDE,
            Conge::TYPE_RTT,
        ];

        $etats = [
            Conge::ETAT_BROUILLON,
            Conge::ETAT_SOUMIS,
            Conge::ETAT_VALIDE_MANAGER,
            Conge::ETAT_APPROUVE,
            Conge::ETAT_REFUSE_MANAGER,
            Conge::ETAT_ANNULE,
        ];

        $etat = fake()->randomElement($etats);

        return [
            'employe_id' => User::factory(),
            'type' => fake()->randomElement($types),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'nombre_jours' => $duree,
            'raison' => fake()->optional(0.7)->sentence(),
            'etat' => $etat,
            'created_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'updated_at' => now(),
        ];
    }

    public function soumis(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_SOUMIS,
        ]);
    }

    public function valideManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_VALIDE_MANAGER,
        ]);
    }

    public function valideRH(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_VALIDE_RH,
        ]);
    }

    public function approuve(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_APPROUVE,
        ]);
    }

    public function refuseManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_REFUSE_MANAGER,
            'raison' => fake()->sentence(),
        ]);
    }

    public function refuseRH(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_REFUSE_RH,
            'raison' => fake()->sentence(),
        ]);
    }

    public function annule(): static
    {
        return $this->state(fn (array $attributes) => [
            'etat' => Conge::ETAT_ANNULE,
        ]);
    }

    public function annuel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conge::TYPE_CONGE_PAYE,
            'nombre_jours' => fake()->numberBetween(1, 25),
        ]);
    }

    public function maladie(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conge::TYPE_MALADIE,
            'nombre_jours' => fake()->numberBetween(1, 5),
        ]);
    }

    public function maternite(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conge::TYPE_MATERNITE,
            'nombre_jours' => 84, // 12 semaines
        ]);
    }

    public function paternite(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conge::TYPE_PATERNITE,
            'nombre_jours' => 28, // 4 semaines (ajout 2021)
        ]);
    }

    public function sansSolde(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conge::TYPE_CONGE_SANS_SOLDE,
        ]);
    }

    public function pourEmploye(int $employeId): static
    {
        return $this->state(fn (array $attributes) => [
            'employe_id' => $employeId,
        ]);
    }
}
