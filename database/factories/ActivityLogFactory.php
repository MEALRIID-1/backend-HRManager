<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        $actions = [
            ActivityLog::ACTION_CREATED,
            ActivityLog::ACTION_UPDATED,
            ActivityLog::ACTION_DELETED,
            ActivityLog::ACTION_APPROVED,
            ActivityLog::ACTION_REJECTED,
            ActivityLog::ACTION_TERMINATED,
        ];

        $entities = ['User', 'Contrat', 'Conge', 'FichePaie', 'Validation'];

        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement($actions),
            'entity_name' => fake()->randomElement($entities),
            'entity_id' => fake()->numberBetween(1, 100),
            'old_values' => fake()->optional(0.5) ? ['statut' => 'brouillon'] : null,
            'new_values' => ['statut' => fake()->randomElement(['soumis', 'approuve', 'termine'])],
            'description' => fake()->optional(0.7)->sentence(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'updated_at' => now(),
        ];
    }

    public function forEntity(string $entityName, int $entityId): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_name' => $entityName,
            'entity_id' => $entityId,
        ]);
    }

    public function byUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}
