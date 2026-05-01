<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'matricule' => fake()->unique()->regexify('[A-Z]{3}-[0-9]{3}'),
            'telephone' => fake()->phoneNumber(),
            'adresse' => fake()->address(),
            'poste' => fake()->jobTitle(),
            'departement' => fake()->randomElement(['Commercial', 'IT', 'RH', 'Finance', 'Production']),
            'date_embauche' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'mot_de_passe' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'dernier_changement_password' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
