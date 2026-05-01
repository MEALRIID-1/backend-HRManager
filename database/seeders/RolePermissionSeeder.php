<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::transaction(function () {
                // Récupérer les rôles déjà créés
                $adminRole = Role::where('slug', 'admin')->first();
                $rhRole = Role::where('slug', 'rh')->first();
                $managerRole = Role::where('slug', 'manager')->first();
                $employeRole = Role::where('slug', 'employe')->first();

                if (!$adminRole || !$rhRole || !$managerRole || !$employeRole) {
                    throw new \Exception('Les rôles doivent être créés avant d\'assigner les permissions');
                }

                // Admin a toutes les permissions
                $adminRole->permissions()->attach(Permission::all()->pluck('id'));
                $this->command->info('✓ Permissions assignées à Admin');

                // RH - Permissions étendues
                $rhPermissions = Permission::whereIn('slug', [
                    // Employés
                    'employes.voir', 'employes.creer', 'employes.modifier', 'employes.restaurer',
                    // Congés (validation N2)
                    'conges.voir', 'conges.creer', 'conges.modifier', 'conges.valider_n2',
                    // Contrats
                    'contrats.voir', 'contrats.creer', 'contrats.modifier', 'contrats.imprimer', 'contrats.telecharger',
                    // Rapports
                    'rapports.voir', 'rapports.exporter',
                    // Paramètres (lecture seule)
                    'parametres.profil',
                    // Fiches de paie
                    'fiches_paie.voir', 'fiches_paie.creer', 'fiches_paie.telecharger',
                ])->pluck('id');
                $this->command->info("  RH permissions: " . $rhPermissions->count());
                $rhRole->permissions()->attach($rhPermissions);
                $this->command->info('✓ Permissions assignées à RH');

                // Manager - Permissions limitées à son équipe
                $managerPermissions = Permission::whereIn('slug', [
                    // Employés (voir seulement)
                    'employes.voir',
                    // Congés (validation N1)
                    'conges.voir', 'conges.creer', 'conges.valider_n1',
                    // Contrats (voir seulement)
                    'contrats.voir',
                    // Rapports (voir seulement)
                    'rapports.voir',
                    // Paramètres profil
                    'parametres.profil',
                ])->pluck('id');
                $managerRole->permissions()->attach($managerPermissions);
                $this->command->info('✓ Permissions assignées à Manager');

                // Employé - Permissions minimales
                $employePermissions = Permission::whereIn('slug', [
                    // Employés (voir seulement son profil)
                    'employes.voir',
                    // Congés (créer/voir ses congés)
                    'conges.voir', 'conges.creer',
                    // Contrats (voir son contrat)
                    'contrats.voir',
                    // Paramètres profil
                    'parametres.profil',
                    // Fiches de paie (voir/telecharger ses fiches)
                    'fiches_paie.voir', 'fiches_paie.telecharger',
                ])->pluck('id');
                $employeRole->permissions()->attach($employePermissions);
                $this->command->info('✓ Permissions assignées à Employé');
            });
        } catch (\Exception $e) {
            Log::error('Erreur RolePermissionSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
