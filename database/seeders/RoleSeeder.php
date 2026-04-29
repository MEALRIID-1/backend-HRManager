<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Vérifier si la table roles existe
        if (!Schema::hasTable('roles')) {
            $this->command->warn('Table roles non trouvée. Création manuelle...');
            
            // Créer la table roles manuellement
            Schema::create('roles', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
            
            // Créer la table role_user (pivot)
            if (!Schema::hasTable('role_user')) {
                Schema::create('role_user', function ($table) {
                    $table->id();
                    $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
                    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                    $table->timestamps();
                });
            }
        }
        
        // Insérer les rôles directement
        $roles = ['admin', 'rh', 'manager', 'employe'];
        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'name' => $role,
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        $this->command->info('Rôles créés avec succès: ' . implode(', ', $roles));
    }
}