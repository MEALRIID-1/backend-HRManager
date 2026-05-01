<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $nom = fake()->jobTitle();

        return [
            'nom' => $nom,
            'slug' => str_slug($nom),
            'description' => fake()->sentence(),
            'niveau_validation' => fake()->numberBetween(0, 3),
            'is_active' => true,
        ];
    }
}
