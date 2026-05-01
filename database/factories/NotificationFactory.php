<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['conge', 'contrat', 'fiche_paie', 'system']),
            'titre' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'action_url' => fake()->optional()->url(),
            'icone' => fake()->optional()->randomElement(['bell', 'check', 'info', 'warning']),
            'lu' => fake()->boolean(20), // 20% chance d'être lu
            'date_lecture' => null,
            'data' => null,
            'reference_id' => null,
            'reference_type' => null,
        ];
    }

    public function nonLue(): static
    {
        return $this->state(fn (array $attributes) => [
            'lu' => false,
            'date_lecture' => null,
        ]);
    }

    public function lue(): static
    {
        return $this->state(fn (array $attributes) => [
            'lu' => true,
            'date_lecture' => now(),
        ]);
    }
}
