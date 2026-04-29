<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
        ]);

        // En environnement de développement, charger aussi les données de démo
        if (app()->environment('local', 'development', 'dev', 'testing')) {
            $this->call([
                DemoSeeder::class,
                TestEmployeeSeeder::class, // Données spécifiques pour tester l'interface employé
            ]);
        }
    }
}
