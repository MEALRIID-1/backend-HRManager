<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('slug', 'admin')->first();

        $admin = User::create([
            'nom' => 'Administrateur',
            'prenom' => 'Admin',
            'email' => 'admin@hrmanager.local',
            'mot_de_passe' => Hash::make('Admin@2024!'),
            'matricule' => 'ADM-2024-0001',
            'poste' => 'Administrateur Système',
            'departement' => 'IT',
            'date_embauche' => now(),
            'dernier_changement_password' => now(),
        ]);

        $admin->roles()->attach($adminRole->id);

        $this->command->info('Admin user created: admin@hrmanager.local / Admin@2024!');
    }
}
