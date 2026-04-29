<?php

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
            'type' => fake()->randomElement([
                'conge_submitted',
                'conge_approved',
                'conge_rejected',
                'payslip_available',
                'contract_expiring',
                'general',
            ]),
            'title' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'data' => ['ref' => fake()->uuid()],
            'read_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'action_url' => fake()->optional()->url(),
            'icon' => fake()->randomElement(['bell', 'check', 'warning', 'info']),
            'created_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'updated_at' => now(),
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }
}
