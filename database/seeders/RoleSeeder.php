<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $roles = [
                    [
                        'name' => 'Administrateur',
                        'slug' => 'admin',
                        'description' => 'Accès complet à toutes les fonctionnalités du système',
                        'niveau_hierarchique' => 3,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Ressources Humaines',
                        'slug' => 'rh',
                        'description' => 'Gestion des employés, contrats et fiches de paie',
                        'niveau_hierarchique' => 2,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Manager',
                        'slug' => 'manager',
                        'description' => 'Validation des congés et gestion d\'équipe',
                        'niveau_hierarchique' => 1,
                        'is_active' => true,
                    ],
                    [
                        'name' => 'Employé',
                        'slug' => 'employe',
                        'description' => 'Accès aux fonctionnalités de base',
                        'niveau_hierarchique' => 0,
                        'is_active' => true,
                    ],
                ];

                foreach ($roles as $roleData) {
                    Role::create($roleData);
                    $this->command->info("✓ Rôle créé : {$roleData['name']} (N{$roleData['niveau_hierarchique']})");
                }
            });
        } catch (\Exception $e) {
            Log::error('Erreur RoleSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
