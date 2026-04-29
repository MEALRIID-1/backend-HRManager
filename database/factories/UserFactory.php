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
     * Départements réalistes pour une entreprise.
     */
    public const DEPARTEMENTS = [
        'Direction Générale',
        'Ressources Humaines',
        'Informatique',
        'Commercial',
        'Marketing',
        'Finance',
        'Production',
        'Logistique',
        'Qualité',
        'Juridique',
    ];

    /**
     * Postes par département.
     */
    public const POSTES = [
        'Direction Générale' => ['Directeur Général', 'Directeur Adjoint', 'Assistant de Direction'],
        'Ressources Humaines' => ['Responsable RH', 'Chargé de Recrutement', 'Gestionnaire Paie', 'Assistant RH'],
        'Informatique' => ['Lead Developer', 'Développeur Full Stack', 'DevOps', 'Admin Système', 'Support IT'],
        'Commercial' => ['Responsable Commercial', 'Account Manager', 'Commercial Sédentaire', 'Téléprospecteur'],
        'Marketing' => ['Responsable Marketing', 'Chef de Projet Digital', 'Community Manager', 'Graphiste'],
        'Finance' => ['Directeur Financier', 'Contrôleur de Gestion', 'Comptable', 'Analyste Financier'],
        'Production' => ['Responsable Production', 'Chef d\'Équipe', 'Technicien', 'Opérateur'],
        'Logistique' => ['Responsable Logistique', 'Magasinier', 'Préparateur de Commandes'],
        'Qualité' => ['Responsable Qualité', 'Auditeur Qualité', 'Technicien Qualité'],
        'Juridique' => ['Directeur Juridique', 'Juriste', 'Conseiller Compliance'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $genre = fake()->randomElement(['male', 'female']);
        $prenom = fake()->firstName($genre);
        $nom = fake()->lastName();

        return [
            'prenom' => $prenom,
            'nom' => $nom,
            'name' => $prenom . ' ' . $nom,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'telephone' => $this->generateFrenchPhone(),
            'date_embauche' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'est_actif' => fake()->boolean(90),
            'departement_id' => null,
            'manager_id' => null, // Sera défini dans le seeder
            'photo' => null,
            'adresse' => fake()->streetAddress(),
        ];
    }

    /**
     * Génère un numéro de téléphone français réaliste.
     */
    private function generateFrenchPhone(): string
    {
        $prefixes = ['06', '07', '01', '02', '03', '04', '05'];
        $prefix = fake()->randomElement($prefixes);
        return $prefix . fake()->numerify(' ## ## ## ##');
    }

    /**
     * Configure un utilisateur comme admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_embauche' => fake()->dateTimeBetween('-10 years', '-5 years')->format('Y-m-d'),
            'est_actif' => true,
        ]);
    }

    /**
     * Configure un utilisateur comme RH.
     */
    public function rh(): static
    {
        return $this->state(fn (array $attributes) => [
            'est_actif' => true,
        ]);
    }

    /**
     * Configure un utilisateur comme manager.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_embauche' => fake()->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'est_actif' => true,
        ]);
    }

    /**
     * Configure un utilisateur comme employé standard.
     */
    public function employe(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_embauche' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
            'est_actif' => true,
        ]);
    }

    /**
     * Crée un utilisateur avec un manager assigné.
     */
    public function withManager(int $managerId): static
    {
        return $this->state(fn (array $attributes) => [
            'manager_id' => $managerId,
        ]);
    }

    /**
     * Crée un employé dans un département spécifique.
     */
    public function inDepartment(string $departement): static
    {
        return $this->state(fn (array $attributes) => [
            'departement_id' => null,
        ]);
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

    /**
     * Marque l'utilisateur comme inactif.
     */
    public function inactif(): static
    {
        return $this->state(fn (array $attributes) => [
            'est_actif' => false,
            'date_depart' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
        ]);
    }
}
