<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContratSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $employes = User::whereHas('roles', function ($query) {
                    $query->whereIn('slug', ['employe', 'manager', 'rh']);
                })->get();

                $typesContrat = ['CDI', 'CDD', 'Stage', 'Alternance'];
                $salaires = [
                    'CDI' => [2500, 4500],
                    'CDD' => [2000, 3500],
                    'Stage' => [800, 1200],
                    'Alternance' => [1000, 1500],
                ];

                foreach ($employes as $employe) {
                    // Créer 1-2 contrats par employé
                    $nbContrats = fake()->numberBetween(1, 2);
                    
                    for ($i = 0; $i < $nbContrats; $i++) {
                        $type = fake()->randomElement($typesContrat);
                        $salaireMin = $salaires[$type][0];
                        $salaireMax = $salaires[$type][1];
                        
                        $dateDebut = $employe->date_embauche->copy()->addMonths($i * 12);
                        $dateFin = in_array($type, ['CDI']) ? null : $dateDebut->copy()->addMonths(fake()->numberBetween(6, 24));
                        
                        Contrat::factory()->create([
                            'user_id' => $employe->id,
                            'type' => $type,
                            'date_debut' => $dateDebut,
                            'date_fin' => $dateFin,
                            'salaire_brut' => fake()->randomFloat(2, $salaireMin, $salaireMax),
                            'statut' => $dateFin && $dateFin->isPast() ? 'termine' : 'actif',
                        ]);
                    }
                    
                    $this->command->info("✓ Contrat(s) créé(s) pour : {$employe->prenom} {$employe->nom}");
                }

                $this->command->info('✓ Contrats créés avec succès');
            });
        } catch (\Exception $e) {
            Log::error('Erreur ContratSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}
