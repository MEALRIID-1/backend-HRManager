<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CongeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $employes = User::whereHas('roles', function ($query) {
                    $query->where('slug', 'employe');
                })->get();

                $typesConge = ['conge_paye', 'conge_sans_solde', 'rtt', 'maladie', 'formation'];
                $etats = ['en_attente', 'approuve', 'refuse', 'partiellement_valide'];
                
                $motifsRefus = [
                    'Manque de personnel sur la période demandée',
                    'Période trop chargée',
                    'Délai de prévenance insuffisant',
                    'Solde de congés insuffisant',
                ];

                foreach ($employes as $employe) {
                    if ($employe->conges()->exists()) {
                        $this->command->info("⚠ Congés déjà existants pour {$employe->prenom} {$employe->nom}, ignorés.");
                        continue;
                    }

                    $nbConges = fake()->numberBetween(2, 4);

                    for ($i = 0; $i < $nbConges; $i++) {
                        $type = fake()->randomElement($typesConge);
                        $etat = fake()->randomElement($etats);

                        $dateDebut = fake()->dateTimeBetween('-6 months', '+3 months');
                        $duree = fake()->numberBetween(1, 15);
                        $dateFin = (clone $dateDebut)->modify("+{$duree} days");

                        $congeData = [
                            'user_id' => $employe->id,
                            'type' => $type,
                            'date_debut' => $dateDebut,
                            'date_fin' => $dateFin,
                            'statut' => $etat,
                            'niveau_validation' => match($etat) {
                                'en_attente' => 0,
                                'partiellement_valide' => fake()->numberBetween(1, 2),
                                'approuve', 'refuse' => 3,
                                default => 0,
                            },
                            'commentaire' => fake()->optional(0.7)->sentence(),
                        ];

                        if ($etat === 'refuse') {
                            $congeData['motif'] = fake()->randomElement($motifsRefus);
                        }

                        Conge::factory()->create($congeData);
                    }

                    $this->command->info("✓ Congés créés pour : {$employe->prenom} {$employe->nom} ({$nbConges} demandes)");
                }

                // Créer quelques congés pour le manager test
                $manager = User::where('email', 'manager@hrmanager.com')->first();
                if ($manager && !$manager->conges()->exists()) {
                    for ($i = 0; $i < 3; $i++) {
                        $dateDebut = fake()->dateTimeBetween('-3 months', '+2 months');
                        $duree = fake()->numberBetween(2, 10);
                        $dateFin = (clone $dateDebut)->modify("+{$duree} days");

                        Conge::factory()->create([
                            'user_id' => $manager->id,
                            'type' => fake()->randomElement($typesConge),
                            'date_debut' => $dateDebut,
                            'date_fin' => $dateFin,
                            'statut' => fake()->randomElement(['en_attente', 'approuve']),
                            'niveau_validation' => fake()->randomElement([0, 3]),
                            'commentaire' => 'Congés manager test',
                        ]);
                    }
                    $this->command->info("✓ Congés créés pour le manager");
                }

                $this->command->info('✓ Congés créés avec succès');
            });
        } catch (\Exception $e) {
            Log::error('Erreur CongeSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
