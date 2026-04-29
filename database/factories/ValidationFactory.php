<?php

namespace Database\Factories;

use App\Models\Conge;
use App\Models\User;
use App\Models\Validation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Validation>
 */
class ValidationFactory extends Factory
{
    protected $model = Validation::class;

    public function definition(): array
    {
        $niveau = fake()->randomElement([
            Validation::NIVEAU_MANAGER,
            Validation::NIVEAU_RH,
            Validation::NIVEAU_DIRECTEUR,
        ]);

        $action = fake()->randomElement([
            Validation::ACTION_APPROUVE,
            Validation::ACTION_APPROUVE,
            Validation::ACTION_APPROUVE,
            Validation::ACTION_REFUSE, // 25% de refus
        ]);

        return [
            'conge_id' => Conge::factory(),
            'valideur_id' => User::factory(),
            'niveau' => $niveau,
            'statut' => $action,
            'commentaire' => $action === Validation::ACTION_REFUSE 
                ? fake()->sentence() 
                : fake()->optional(0.5)->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function approbation(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => Validation::ACTION_APPROUVE,
            'commentaire' => fake()->optional(0.7)->sentence(),
        ]);
    }

    public function refus(): static
    {
        return $this->state(fn (array $attributes) => [
            'statut' => Validation::ACTION_REFUSE,
            'commentaire' => fake()->sentence(),
        ]);
    }

    public function niveauManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => Validation::NIVEAU_MANAGER,
        ]);
    }

    public function niveauRH(): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => Validation::NIVEAU_RH,
        ]);
    }

    public function niveauDirecteur(): static
    {
        return $this->state(fn (array $attributes) => [
            'niveau' => Validation::NIVEAU_DIRECTEUR,
        ]);
    }

    public function pourConge(int $congeId): static
    {
        return $this->state(fn (array $attributes) => [
            'conge_id' => $congeId,
        ]);
    }

    public function parValidateur(int $validateurId): static
    {
        return $this->state(fn (array $attributes) => [
            'valideur_id' => $validateurId,
        ]);
    }
}
