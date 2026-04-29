<?php

namespace Database\Seeders;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de démonstration pour créer des données réalistes.
 * Crée une hiérarchie complète: 1 admin, 2 RH, 5 managers, 20 employés.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->command->info('Création des données de démonstration...');

        // 1. Créer l'admin
        $admin = $this->createAdmin();
        $this->command->info('Admin créé: ' . $admin->email);

        // 2. Créer les RH
        $rhs = $this->createRH();
        $this->command->info(count($rhs) . ' RH créés');

        // 3. Créer les managers (un par département)
        $managers = $this->createManagers();
        $this->command->info(count($managers) . ' Managers créés');

        // 4. Créer les employés avec managers assignés
        $employes = $this->createEmployes($managers);
        $this->command->info(count($employes) . ' Employés créés');

        // 5. Créer les contrats pour chaque employé
        $this->createContrats($employes);
        $this->command->info('Contrats créés');

        // 6. Créer les congés
        $this->createConges($employes, $managers);
        $this->command->info('Congés créés');

        // 7. Créer les fiches de paie
        $this->createFichesPaie($employes);
        $this->command->info('Fiches de paie créées');

        // 8. Créer les notifications pour les managers
        $this->createNotifications($managers);
        $this->command->info('Notifications créées');

        $this->command->info('✅ Données de démonstration créées avec succès !');
    }

    private function createAdmin(): User
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@hrmanager.com'],
            [
                'prenom' => 'Super',
                'nom' => 'Admin',
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'est_actif' => true,
                'date_embauche' => '2020-01-15',
            ]
        );

        $admin->assignRole('admin');

        return $admin;
    }

    private function createRH(): array
    {
        $rhData = [
            ['prenom' => 'Marie', 'nom' => 'Durand', 'name' => 'Marie Durand', 'email' => 'rh1@hrmanager.com'],
            ['prenom' => 'Pierre', 'nom' => 'Martin', 'name' => 'Pierre Martin', 'email' => 'rh2@hrmanager.com'],
        ];

        $rhs = [];
        foreach ($rhData as $data) {
            $rh = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, [
                    'password' => Hash::make('password123'),
                    'est_actif' => true,
                    'date_embauche' => $data['email'] === 'rh1@hrmanager.com' ? '2021-03-10' : '2022-09-05',
                ])
            );
            $rh->assignRole('rh');
            $rhs[] = $rh;
        }

        return $rhs;
    }

    private function createManagers(): array
    {
        $departements = [
            'Informatique' => ['Jean', 'Dupont'],
            'Commercial' => ['Sophie', 'Lefebvre'],
            'Marketing' => ['Lucas', 'Bernard'],
            'Finance' => ['Emma', 'Moreau'],
            'Production' => ['Thomas', 'Petit'],
        ];

        $managers = [];
        foreach ($departements as $departement => $names) {
            $manager = User::updateOrCreate(
                ['email' => strtolower($names[0] . '.' . $names[1] . '@hrmanager.com')],
                [
                    'prenom' => $names[0],
                    'nom' => $names[1],
                    'name' => $names[0] . ' ' . $names[1],
                    'password' => Hash::make('password123'),
                    'est_actif' => true,
                    'date_embauche' => fake()->dateTimeBetween('-4 years', '-1 year')->format('Y-m-d'),
                ]
            );
            $manager->assignRole('manager');
            $managers[$departement] = $manager;
        }

        return $managers;
    }

    private function createEmployes(array $managers): array
    {
        $employes = [];
        $departements = array_keys($managers);

        // 4 employés par département
        foreach ($departements as $departement) {
            $manager = $managers[$departement];

            for ($i = 0; $i < 4; $i++) {
                $email = strtolower($departement . '.employe' . ($i + 1) . '@hrmanager.com');
                $employe = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'prenom' => 'Employe' . ($i + 1),
                        'nom' => $departement,
                        'name' => 'Employe' . ($i + 1) . ' ' . $departement,
                        'password' => Hash::make('password123'),
                        'est_actif' => true,
                        'manager_id' => $manager->id,
                        'date_embauche' => fake()->dateTimeBetween('-3 years', '-3 months')->format('Y-m-d'),
                    ]
                );
                $employe->assignRole('employe');
                $employes[] = $employe;
            }
        }

        return $employes;
    }

    private function createContrats(array $employes): void
    {
        foreach ($employes as $employe) {
            Contrat::factory()
                ->pourEmploye($employe->id)
                ->actif()
                ->create([
                    'date_debut' => $employe->date_embauche,
                    'salaire' => fake()->randomFloat(2, 2500, 5000),
                ]);
        }
    }

    private function createConges(array $employes, array $managers): void
    {
        foreach ($employes as $index => $employe) {
            // Créer 2-3 congés par employé avec des états variés
            $nbConges = fake()->numberBetween(2, 3);

            for ($i = 0; $i < $nbConges; $i++) {
                $etat = fake()->randomElement([
                    Conge::ETAT_APPROUVE,
                    Conge::ETAT_SOUMIS,
                    Conge::ETAT_VALIDE_MANAGER,
                    Conge::ETAT_REFUSE_MANAGER,
                ]);

                $conge = Conge::factory()
                    ->pourEmploye($employe->id)
                    ->annuel()
                    ->create([
                        'etat' => $etat,
                        'date_debut' => fake()->dateTimeBetween('-3 months', '+3 months'),
                        'nombre_jours' => fake()->numberBetween(1, 10),
                    ]);

                // Créer des validations selon l'état
                if ($etat === Conge::ETAT_VALIDE_MANAGER || $etat === Conge::ETAT_APPROUVE) {
                    \App\Models\Validation::factory()
                        ->pourConge($conge->id)
                        ->parValidateur($employe->manager_id)
                        ->approbation()
                        ->niveauManager()
                        ->create();
                } elseif ($etat === Conge::ETAT_REFUSE_MANAGER) {
                    \App\Models\Validation::factory()
                        ->pourConge($conge->id)
                        ->parValidateur($employe->manager_id)
                        ->refus()
                        ->niveauManager()
                        ->create();
                }
            }
        }
    }

    private function createFichesPaie(array $employes): void
    {
        $annee = now()->year
        $moisActuel = now()->month;

        foreach ($employes as $employe) {
            // Créer 6 mois de fiches de paie (janvier à juin)
            for ($mois = 1; $mois <= min(6, $moisActuel); $mois++) {
                FichePaie::factory()
                    ->pourEmploye($employe->id)
                    ->pourPeriode($mois, $annee)
                    ->envoye()
                    ->create();
            }
        }
    }

    private function createNotifications(array $managers): void
    {
        foreach ($managers as $manager) {
            // Créer 3-5 notifications non lues pour chaque manager
            $nbNotifs = fake()->numberBetween(3, 5);

            Notification::factory()
                ->count($nbNotifs)
                ->unread()
                ->create([
                    'user_id' => $manager->id,
                    'type' => 'conge_submitted',
                    'title' => 'Nouvelle demande de congé',
                    'message' => fake()->sentence(),
                ]);
        }
    }
}
