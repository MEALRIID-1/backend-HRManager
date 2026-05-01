<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $permissions = [
                    // Module Employés
                    ['name' => 'Voir employés', 'slug' => 'employes.voir', 'module' => 'employes'],
                    ['name' => 'Créer employés', 'slug' => 'employes.creer', 'module' => 'employes'],
                    ['name' => 'Modifier employés', 'slug' => 'employes.modifier', 'module' => 'employes'],
                    ['name' => 'Supprimer employés', 'slug' => 'employes.supprimer', 'module' => 'employes'],
                    ['name' => 'Restaurer employés', 'slug' => 'employes.restaurer', 'module' => 'employes'],

                    // Module Congés
                    ['name' => 'Voir congés', 'slug' => 'conges.voir', 'module' => 'conges'],
                    ['name' => 'Créer congés', 'slug' => 'conges.creer', 'module' => 'conges'],
                    ['name' => 'Modifier congés', 'slug' => 'conges.modifier', 'module' => 'conges'],
                    ['name' => 'Supprimer congés', 'slug' => 'conges.supprimer', 'module' => 'conges'],
                    ['name' => 'Valider congés N1', 'slug' => 'conges.valider_n1', 'module' => 'conges'],
                    ['name' => 'Valider congés N2', 'slug' => 'conges.valider_n2', 'module' => 'conges'],
                    ['name' => 'Valider congés N3', 'slug' => 'conges.valider_n3', 'module' => 'conges'],
                    ['name' => 'Super validation congés', 'slug' => 'conges.super_validation', 'module' => 'conges'],

                    // Module Contrats
                    ['name' => 'Voir contrats', 'slug' => 'contrats.voir', 'module' => 'contrats'],
                    ['name' => 'Créer contrats', 'slug' => 'contrats.creer', 'module' => 'contrats'],
                    ['name' => 'Modifier contrats', 'slug' => 'contrats.modifier', 'module' => 'contrats'],
                    ['name' => 'Supprimer contrats', 'slug' => 'contrats.supprimer', 'module' => 'contrats'],
                    ['name' => 'Imprimer contrats', 'slug' => 'contrats.imprimer', 'module' => 'contrats'],
                    ['name' => 'Télécharger contrats', 'slug' => 'contrats.telecharger', 'module' => 'contrats'],

                    // Module Rapports
                    ['name' => 'Voir rapports', 'slug' => 'rapports.voir', 'module' => 'rapports'],
                    ['name' => 'Exporter rapports', 'slug' => 'rapports.exporter', 'module' => 'rapports'],

                    // Module Paramètres
                    ['name' => 'Gérer rôles', 'slug' => 'parametres.roles', 'module' => 'parametres'],
                    ['name' => 'Gérer permissions', 'slug' => 'parametres.permissions', 'module' => 'parametres'],
                    ['name' => 'Gérer profil', 'slug' => 'parametres.profil', 'module' => 'parametres'],

                    // Module Fiches de Paie
                    ['name' => 'Voir fiches de paie', 'slug' => 'fiches_paie.voir', 'module' => 'fiches_paie'],
                    ['name' => 'Créer fiches de paie', 'slug' => 'fiches_paie.creer', 'module' => 'fiches_paie'],
                    ['name' => 'Télécharger fiches de paie', 'slug' => 'fiches_paie.telecharger', 'module' => 'fiches_paie'],
                ];

                foreach ($permissions as $permData) {
                    Permission::create($permData);
                    $this->command->info("✓ Permission créée : {$permData['slug']}");
                }
            });
        } catch (\Exception $e) {
            Log::error('Erreur PermissionSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
