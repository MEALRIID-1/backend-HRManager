<?php

namespace Database\Seeders;

use App\Models\Conge;
use App\Models\Contrat;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder pour tester l'interface employé.
 * Crée un employé de test avec toutes les données nécessaires.
 */
class TestEmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->command->info('Création des données de test pour employé...');

        // 1. Créer un manager (nécessaire pour l'employé)
        $managerEmail = 'manager.test@hrmanager.com';
        $manager = User::where('email', $managerEmail)->first();
        if (!$manager) {
            $manager = User::factory()
                ->manager()
                ->inDepartment('Informatique')
                ->create([
                    'prenom' => 'Manager',
                    'nom' => 'Test',
                    'name' => 'Manager Test',
                    'email' => $managerEmail,
                    'password' => Hash::make('password123'),
                ]);
            $manager->assignRole('manager');
            $this->command->info('Manager créé: ' . $managerEmail);
        } else {
            // Si le manager existe mais n'a pas le rôle, l'assigner (idempotent)
            if (method_exists($manager, 'hasRole') && !$manager->hasRole('manager')) {
                $manager->assignRole('manager');
                $this->command->info('Role manager assigné à l\'utilisateur existant: ' . $managerEmail);
            }
            $this->command->info('Manager déjà existant: ' . $managerEmail);
        }

        // 2. Créer l'employé de test
        $employeEmail = 'employe.demo@hrmanager.com';
        $employe = User::where('email', $employeEmail)->first();
        if (!$employe) {
            $employeData = [
                'prenom' => 'Employe',
                'nom' => 'Demo',
                'name' => 'Employe Demo',
                'email' => $employeEmail,
                'password' => Hash::make('password123'),
                'telephone' => '0612345678',
                'adresse' => '123 Rue de la Paix, 75000 Paris',
                'date_embauche' => Carbon::create(2022, 1, 10),
            ];

            if (Schema::hasColumn('users', 'date_naissance')) {
                $employeData['date_naissance'] = Carbon::create(1990, 5, 15);
            }

            $employe = User::factory()
                ->employe()
                ->inDepartment('Informatique')
                ->withManager($manager->id)
                ->create($employeData);

            $employe->assignRole('employe');
            $this->command->info('Employé créé: ' . $employeEmail);
        } else {
            $this->command->info('Employé déjà existant: ' . $employeEmail);
        }

        // 3. Créer le contrat actif
        $this->createContrat($employe);
        $this->command->info('Contrat actif créé');

        // 4. Créer des congés de test
        $this->createConges($employe);
        $this->command->info('Congés créés (actifs + annulés)');

        // 5. Créer des notifications
        $this->createNotifications($employe);
        $this->command->info('Notifications créées');

        // 6. Créer un congé annulé pour tester la corbeille
        $this->createCancelledConge($employe);
        $this->command->info('Congé annulé créé pour la corbeille');

        $this->command->info('');
        $this->command->info('✅ Données de test créées avec succès !');
        $this->command->info('');
        $this->command->info('Identifiants de connexion:');
        $this->command->info('  Email: employe.demo@hrmanager.com');
        $this->command->info('  Mot de passe: password123');
        $this->command->info('');
        $this->command->info('Routes API disponibles:');
        $this->command->info('  GET    /api/me');
        $this->command->info('  GET    /api/me/contrats');
        $this->command->info('  GET    /api/me/contrats/actif');
        $this->command->info('  GET    /api/me/conges');
        $this->command->info('  GET    /api/me/conges/solde');
        $this->command->info('  GET    /api/me/conges/corbeille/count');
        $this->command->info('  POST   /api/me/conges');
        $this->command->info('  DELETE /api/me/conges/{id}');
        $this->command->info('  PUT    /api/me/conges/{id}/restaurer');
        $this->command->info('  GET    /api/notifications');
        $this->command->info('  GET    /api/notifications/count-non-lues');
    }

    private function createContrat(User $employe): void
    {
        $data = [
            'employe_id' => $employe->id,
            'type' => 'cdi',
            'date_debut' => $employe->date_embauche,
            'date_fin' => null,
            'salaire' => 3500.00,
            'etat' => 'en_cours',
        ];

        if (Schema::hasColumn('contrats', 'reference')) {
            $data['reference'] = 'CDI-' . strtoupper(fake()->bothify('??###??'));
        }

        Contrat::create($data);
    }

    private function createConges(User $employe): void
    {
        $aujourdhui = Carbon::now();

        // Congé approuvé (passé)
        $data1 = [
            'employe_id' => $employe->id,
            'type' => 'conge_annuel',
            'date_debut' => $aujourdhui->copy()->subMonths(2),
            'date_fin' => $aujourdhui->copy()->subMonths(2)->addDays(4),
            'nombre_jours' => 5,
            'etat' => Conge::ETAT_APPROUVE,
            'created_at' => $aujourdhui->copy()->subMonths(3),
        ];
        if (Schema::hasColumn('conges', 'raison')) {
            $data1['raison'] = 'Vacances d\'hiver';
        }
        Conge::create($data1);

        $data2 = [
            'employe_id' => $employe->id,
            'type' => 'conge_annuel',
            'date_debut' => $aujourdhui->copy()->addMonths(1),
            'date_fin' => $aujourdhui->copy()->addMonths(1)->addDays(7),
            'nombre_jours' => 8,
            'etat' => Conge::ETAT_SOUMIS,
        ];
        if (Schema::hasColumn('conges', 'raison')) {
            $data2['raison'] = 'Vacances d\'été';
        }
        Conge::create($data2);

        $data3 = [
            'employe_id' => $employe->id,
            'type' => 'maladie',
            'date_debut' => $aujourdhui->copy()->subDays(2),
            'date_fin' => $aujourdhui->copy()->addDays(1),
            'nombre_jours' => 4,
            'etat' => Conge::ETAT_VALIDE_MANAGER,
        ];
        if (Schema::hasColumn('conges', 'raison')) {
            $data3['raison'] = 'Arrêt maladie';
        }
        Conge::create($data3);

        $data4 = [
            'employe_id' => $employe->id,
            'type' => 'exceptionnel',
            'date_debut' => $aujourdhui->copy()->addDays(10),
            'date_fin' => $aujourdhui->copy()->addDays(12),
            'nombre_jours' => 3,
            'etat' => Conge::ETAT_REFUSE_MANAGER,
        ];
        if (Schema::hasColumn('conges', 'raison')) {
            $data4['raison'] = 'Événement familial';
        }
        if (Schema::hasColumn('conges', 'commentaire')) {
            $data4['commentaire'] = 'Déjà trop de demandes sur cette période';
        }
        Conge::create($data4);
    }

    private function createCancelledConge(User $employe): void
    {
        $aujourdhui = Carbon::now();

        // Congé annulé pour tester la corbeille
        $data = [
            'employe_id' => $employe->id,
            'type' => 'conge_annuel',
            'date_debut' => $aujourdhui->copy()->addMonths(2),
            'date_fin' => $aujourdhui->copy()->addMonths(2)->addDays(5),
            'nombre_jours' => 6,
            'etat' => Conge::ETAT_ANNULE,
        ];
        if (Schema::hasColumn('conges', 'raison')) {
            $data['raison'] = 'Voyage annulé';
        }
        if (Schema::hasColumn('conges', 'motif_annulation')) {
            $data['motif_annulation'] = 'Changement de plans';
        }
        if (Schema::hasColumn('conges', 'annule_par')) {
            $data['annule_par'] = $employe->id;
        }
        if (Schema::hasColumn('conges', 'annule_le')) {
            $data['annule_le'] = $aujourdhui;
        }

        $conge = Conge::create($data);

        // Soft delete pour simuler la corbeille
        $conge->delete();
    }

    private function createNotifications(User $employe): void
    {
        $aujourdhui = Carbon::now();

        // Notification non lue - congé approuvé
        $n1 = [
            'user_id' => $employe->id,
            'type' => 'CONGE_APPROUVE',
            'message' => 'Votre demande de congé du ' . $aujourdhui->copy()->addMonths(1)->format('d/m/Y') . ' a été approuvée.',
        ];
        if (Schema::hasColumn('notifications', 'lue')) {
            $n1['lue'] = false;
        }
        if (Schema::hasColumn('notifications', 'created_at')) {
            $n1['created_at'] = $aujourdhui->copy()->subDays(2);
        }
        if (Schema::hasColumn('notifications', 'lien_action')) {
            $n1['lien_action'] = '/employe/conges';
        }
        if (Schema::hasColumn('notifications', 'data')) {
            $n1['data'] = json_encode((object)[]);
        }
        if (Schema::hasColumn('notifications', 'titre')) {
            $n1['titre'] = 'Votre congé a été approuvé';
        }
        Notification::create($n1);

        // Notification non lue - congé refusé
        $n2 = [
            'user_id' => $employe->id,
            'type' => 'CONGE_REFUSE',
            'message' => 'Votre demande de congé exceptionnel du ' . $aujourdhui->copy()->addDays(10)->format('d/m/Y') . ' a été refusée.',
        ];
        if (Schema::hasColumn('notifications', 'lue')) {
            $n2['lue'] = false;
        }
        if (Schema::hasColumn('notifications', 'created_at')) {
            $n2['created_at'] = $aujourdhui->copy()->subDay();
        }
        if (Schema::hasColumn('notifications', 'lien_action')) {
            $n2['lien_action'] = '/employe/conges';
        }
        if (Schema::hasColumn('notifications', 'data')) {
            $n2['data'] = json_encode((object)[]);
        }
        if (Schema::hasColumn('notifications', 'titre')) {
            $n2['titre'] = 'Votre congé a été refusé';
        }
        Notification::create($n2);

        // Notification lue - contrat
        $n3 = [
            'user_id' => $employe->id,
            'type' => 'SYSTEME',
            'message' => 'Votre compte a été créé avec succès. Explorez votre espace personnel.',
        ];
        if (Schema::hasColumn('notifications', 'lue')) {
            $n3['lue'] = true;
        }
        if (Schema::hasColumn('notifications', 'created_at')) {
            $n3['created_at'] = $aujourdhui->copy()->subMonths(3);
        }
        if (Schema::hasColumn('notifications', 'lien_action')) {
            $n3['lien_action'] = '/employe/dashboard';
        }
        if (Schema::hasColumn('notifications', 'data')) {
            $n3['data'] = json_encode((object)[]);
        }
        if (Schema::hasColumn('notifications', 'titre')) {
            $n3['titre'] = 'Bienvenue sur HRManager';
        }
        Notification::create($n3);

        // Notification non lue - rappel
        $n4 = [
            'user_id' => $employe->id,
            'type' => 'CONTRAT_EXPIRE_BIENTOT',
            'message' => 'Votre contrat actuel expire dans moins de 3 mois. Contactez votre RH.',
        ];
        if (Schema::hasColumn('notifications', 'lue')) {
            $n4['lue'] = false;
        }
        if (Schema::hasColumn('notifications', 'created_at')) {
            $n4['created_at'] = $aujourdhui->copy()->subHours(2);
        }
        if (Schema::hasColumn('notifications', 'lien_action')) {
            $n4['lien_action'] = '/employe/contrat';
        }
        if (Schema::hasColumn('notifications', 'data')) {
            $n4['data'] = json_encode((object)[]);
        }
        if (Schema::hasColumn('notifications', 'titre')) {
            $n4['titre'] = 'Votre contrat expire bientôt';
        }
        Notification::create($n4);
    }
}
