<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserSeeder extends Seeder
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

                // 1. Admin
                $admin = User::factory()->create([
                    'nom' => 'Dupont',
                    'prenom' => 'Marie',
                    'email' => 'admin@hrmanager.com',
                    'mot_de_passe' => Hash::make('Admin@2024!'),
                    'matricule' => 'ADM-001',
                    'poste' => 'Administratrice Système',
                    'departement' => 'IT',
                    'telephone' => '658236589',
                    'adresse' => '15 Rue de Paris, 75001 Paris',
                    'date_embauche' => now()->subYears(3),
                    'is_active' => true,
                    'dernier_changement_password' => now(),
                ]);
                $admin->roles()->attach($adminRole);
                $this->command->info('✓ Utilisateur créé : admin@hrmanager.com / Admin@2024!');

                // 2. RH
                $rh = User::factory()->create([
                    'nom' => 'Martin',
                    'prenom' => 'Sophie',
                    'email' => 'rh@hrmanager.com',
                    'mot_de_passe' => Hash::make('Rh@2024!'),
                    'matricule' => 'RH-001',
                    'poste' => 'Responsable RH',
                    'departement' => 'Ressources Humaines',
                    'telephone' => '657235698',
                    'adresse' => '28 Avenue des Champs, 75008 Paris',
                    'date_embauche' => now()->subYears(2),
                    'is_active' => true,
                    'dernier_changement_password' => now(),
                ]);
                $rh->roles()->attach($rhRole);
                $this->command->info('✓ Utilisateur créé : rh@hrmanager.com / Rh@2024!');

                // 3. Manager
                $manager = User::factory()->create([
                    'nom' => 'Bernard',
                    'prenom' => 'Lucas',
                    'email' => 'manager@hrmanager.com',
                    'mot_de_passe' => Hash::make('Manager@2024!'),
                    'matricule' => 'MGR-001',
                    'poste' => 'Chef de Département Commercial',
                    'departement' => 'Commercial',
                    'telephone' => '695632569',
                    'adresse' => '45 Boulevard Haussmann, 75009 Paris',
                    'date_embauche' => now()->subMonths(18),
                    'is_active' => true,
                    'dernier_changement_password' => now(),
                ]);
                $manager->roles()->attach($managerRole);
                $this->command->info('✓ Utilisateur créé : manager@hrmanager.com / Manager@2024!');

                // 4. Employé
                $employe = User::factory()->create([
                    'nom' => 'Petit',
                    'prenom' => 'Emma',
                    'email' => 'employe@hrmanager.com',
                    'mot_de_passe' => Hash::make('Employe@2024!'),
                    'matricule' => 'EMP-001',
                    'poste' => 'Commercial',
                    'departement' => 'Commercial',
                    'telephone' => '672569852',
                    'adresse' => '12 Rue du Commerce, 75015 Paris',
                    'date_embauche' => now()->subMonths(6),
                    'is_active' => true,
                    'dernier_changement_password' => now(),
                ]);
                $employe->roles()->attach($employeRole);
                $this->command->info('✓ Utilisateur créé : employe@hrmanager.com / Employe@2024!');

                // 5-14. 10 employés fictifs avec Factory
                $departements = ['Commercial', 'IT', 'RH', 'Finance', 'Production', 'Marketing'];
                $postes = [
                    'Commercial' => ['Commercial', 'Chef de vente', 'Représentant'],
                    'IT' => ['Développeur', 'Administrateur réseau', 'Support technique'],
                    'RH' => ['Assistant RH', 'Recruteur', 'Gestionnaire paie'],
                    'Finance' => ['Comptable', 'Analyste financier', 'Contrôleur de gestion'],
                    'Production' => ['Opérateur', 'Responsable production', 'Technicien'],
                    'Marketing' => ['Chargé de communication', 'Community manager', 'Graphiste'],
                ];

                for ($i = 2; $i <= 11; $i++) {
                    $dept = fake()->randomElement($departements);
                    $poste = fake()->randomElement($postes[$dept]);
                    
                    $employeFictif = User::factory()->create([
                        'matricule' => 'EMP-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT),
                        'poste' => $poste,
                        'departement' => $dept,
                        'date_embauche' => now()->subMonths(fake()->numberBetween(3, 24)),
                    ]);
                    $employeFictif->roles()->attach($employeRole);
                    $this->command->info("✓ Employé fictif créé : {$employeFictif->prenom} {$employeFictif->nom} ({$poste} - {$dept})");
                }
            });
        } catch (\Exception $e) {
            Log::error('Erreur UserSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
