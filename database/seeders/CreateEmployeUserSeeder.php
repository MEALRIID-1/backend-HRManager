<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateEmployeUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $email = 'employe@hrmanager.com';
        $password = 'password1234';

        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Employe Test',
                'prenom' => 'Employe',
                'nom' => 'Test',
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            if (method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole('employe');
                } catch (\Throwable $e) {
                    // Ignore if role doesn't exist in this environment
                }
            }
            $this->command->info('Utilisateur créé: ' . $email);
        } else {
            // Ensure password matches the requested test password (idempotent update)
            $user->password = Hash::make($password);
            $user->save();
            // Ensure role exists/assigned if possible
            if (method_exists($user, 'assignRole')) {
                try {
                    $user->assignRole('employe');
                } catch (\Throwable $e) {
                    // Ignore if role doesn't exist in this environment
                }
            }
            $this->command->info('Utilisateur existant mis à jour (mot de passe réinitialisé): ' . $email);
        }
    }
}
