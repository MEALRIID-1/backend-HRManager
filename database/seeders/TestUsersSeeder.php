<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                // Récupérer les rôles
                $adminRole = Role::where('slug', 'admin')->first();
                $rhRole = Role::where('slug', 'rh')->first();
                $managerRole = Role::where('slug', 'manager')->first();
                $employeRole = Role::where('slug', 'employe')->first();

                // Admin
                $admin = User::firstOrCreate(
                    ['email' => 'admin@hrmanager.com'],
                    [
                        'nom' => 'Admin',
                        'prenom' => 'Administrateur',
                        'mot_de_passe' => Hash::make('Admin@2024!'),
                        'matricule' => 'ADM-001',
                        'poste' => 'Administrateur Système',
                        'departement' => 'IT',
                        'telephone' => '0123456789',
                        'adresse' => '123 Rue Admin',
                        'date_embauche' => now()->subYears(2),
                        'is_active' => true,
                        'dernier_changement_password' => now(),
                    ]
                );
                $admin->roles()->syncWithoutDetaching($adminRole->id);
                $this->command->info('✓ Admin: admin@hrmanager.com / Admin@2024!');

                // RH
                $rh = User::firstOrCreate(
                    ['email' => 'rh@hrmanager.com'],
                    [
                        'nom' => 'RH',
                        'prenom' => 'Responsable',
                        'mot_de_passe' => Hash::make('Rh@2024!'),
                        'matricule' => 'RH-001',
                        'poste' => 'Responsable RH',
                        'departement' => 'Ressources Humaines',
                        'telephone' => '0123456790',
                        'adresse' => '456 Rue RH',
                        'date_embauche' => now()->subYears(1),
                        'is_active' => true,
                        'dernier_changement_password' => now(),
                    ]
                );
                $rh->roles()->syncWithoutDetaching($rhRole->id);
                $this->command->info('✓ RH: rh@hrmanager.com / Rh@2024!');

                // Manager
                $manager = User::firstOrCreate(
                    ['email' => 'manager@hrmanager.com'],
                    [
                        'nom' => 'Manager',
                        'prenom' => 'Chef',
                        'mot_de_passe' => Hash::make('Manager@2024!'),
                        'matricule' => 'MGR-001',
                        'poste' => 'Chef de Département',
                        'departement' => 'Commercial',
                        'telephone' => '0123456791',
                        'adresse' => '789 Rue Manager',
                        'date_embauche' => now()->subMonths(18),
                        'is_active' => true,
                        'dernier_changement_password' => now(),
                    ]
                );
                $manager->roles()->syncWithoutDetaching($managerRole->id);
                $this->command->info('✓ Manager: manager@hrmanager.com / Manager@2024!');

                // Employé
                $employe = User::firstOrCreate(
                    ['email' => 'employe@hrmanager.com'],
                    [
                        'nom' => 'Employe',
                        'prenom' => 'Test',
                        'mot_de_passe' => Hash::make('Employe@2024!'),
                        'matricule' => 'EMP-001',
                        'poste' => 'Employé Commercial',
                        'departement' => 'Commercial',
                        'telephone' => '0123456792',
                        'adresse' => '321 Rue Employe',
                        'date_embauche' => now()->subMonths(6),
                        'is_active' => true,
                        'dernier_changement_password' => now(),
                    ]
                );
                $employe->roles()->syncWithoutDetaching($employeRole->id);
                $this->command->info('✓ Employe: employe@hrmanager.com / Employe@2024!');
            });
        } catch (\Exception $e) {
            Log::error('Erreur seeding utilisateurs de test: ' . $e->getMessage());
            throw $e;
        }
    }
}
