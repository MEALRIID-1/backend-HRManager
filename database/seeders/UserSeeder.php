<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Fonction helper pour créer un utilisateur
        $createUser = function ($data) {
            DB::table('users')->insertOrIgnore([
                'prenom' => $data['prenom'],
                'nom' => $data['nom'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'telephone' => $data['telephone'] ?? null,
                'date_embauche' => $data['date_embauche'] ?? null,
                'est_actif' => $data['est_actif'] ?? true,
                'manager_id' => $data['manager_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('users')->where('email', $data['email'])->value('id');
        };

        // Fonction helper pour assigner un rôle
        $assignRole = function ($userId, $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($roleId) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ]);
            }
        };

        // Admin / Directeur
        $adminId = $createUser([
            'prenom' => 'Super',
            'nom' => 'Admin',
            'name' => 'Super Admin',
            'email' => 'admin@hrmanager.com',
            'password' => 'password123',
            'telephone' => '+33 6 12 34 56 78',
            'date_embauche' => '2020-01-15',
            'est_actif' => true,
        ]);
        $assignRole($adminId, 'admin');

        // RH
        $rhId = $createUser([
            'prenom' => 'Marie',
            'nom' => 'RH',
            'name' => 'Marie RH',
            'email' => 'rh@hrmanager.com',
            'password' => 'password123',
            'telephone' => '+33 6 23 45 67 89',
            'date_embauche' => '2021-03-10',
            'est_actif' => true,
        ]);
        $assignRole($rhId, 'rh');

        // Manager
        $managerId = $createUser([
            'prenom' => 'Pierre',
            'nom' => 'Manager',
            'name' => 'Pierre Manager',
            'email' => 'manager@hrmanager.com',
            'password' => 'password123',
            'telephone' => '+33 6 34 56 78 90',
            'date_embauche' => '2022-06-20',
            'est_actif' => true,
        ]);
        $assignRole($managerId, 'manager');

        // Employé
        $employeId = $createUser([
            'prenom' => 'Jean',
            'nom' => 'Employe',
            'name' => 'Jean Employe',
            'email' => 'employe@hrmanager.com',
            'password' => 'password123',
            'telephone' => '+33 6 45 67 89 01',
            'date_embauche' => '2023-09-01',
            'est_actif' => true,
            'manager_id' => $managerId,
        ]);
        $assignRole($employeId, 'employe');

        // Employé supplémentaire pour l'équipe du manager
        $employe2Id = $createUser([
            'prenom' => 'Sophie',
            'nom' => 'Employe',
            'name' => 'Sophie Employe',
            'email' => 'sophie@hrmanager.com',
            'password' => 'password123',
            'telephone' => '+33 6 56 78 90 12',
            'date_embauche' => '2023-11-15',
            'est_actif' => true,
            'manager_id' => $managerId,
        ]);
        $assignRole($employe2Id, 'employe');

        $this->command->info('5 utilisateurs créés avec rôles assignés');
    }
}