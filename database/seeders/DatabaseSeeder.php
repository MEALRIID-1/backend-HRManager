<?php

declare(strict_types=1);

namespace Database\Seeders;

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
        $this->command->info('🚀 Démarrage du seeding HRManager...');

        $this->call([
            // 1. Rôles (base)
            RoleSeeder::class,

            // 2. Permissions
            PermissionSeeder::class,

            // 3. Assignation permissions aux rôles
            RolePermissionSeeder::class,

            // 4. Utilisateurs (test + fictifs)
            UserSeeder::class,

            // 5. Contrats
            ContratSeeder::class,

            // 6. Congés (à différents états)
            CongeSeeder::class,

            // 7. Fiches de paie de démonstration
            FichePaieSeeder::class,
        ]);

        $this->command->info('✅ Seeding HRManager terminé avec succès !');
        $this->command->info('');
        $this->command->info('📧 Utilisateurs de test :');
        $this->command->info('   admin@hrmanager.com / Admin@2024!');
        $this->command->info('   rh@hrmanager.com / Rh@2024!');
        $this->command->info('   manager@hrmanager.com / Manager@2024!');
        $this->command->info('   employe@hrmanager.com / Employe@2024!');
    }
}
