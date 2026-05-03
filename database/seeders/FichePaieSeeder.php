<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FichePaieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::transaction(function () {
                $users = User::whereIn('email', [
                    'admin@hrmanager.com',
                    'rh@hrmanager.com',
                    'manager@hrmanager.com',
                    'employe@hrmanager.com',
                ])->get()->keyBy('email');

                $fiches = [
                    [
                        'email' => 'admin@hrmanager.com',
                        'periode' => '2025-01',
                        'salaire_brut' => 420000,
                        'salaire_net' => 336000,
                        'heures_travaillees' => 160,
                        'heures_supplementaires' => 8,
                        'absences' => 0,
                        'montant_heures_sup' => 25000,
                        'prime_anciennete' => 40000,
                        'prime_productivite' => 15000,
                        'prime_autres' => 5000,
                        'total_cotisations' => 84000,
                        'total_retenues' => 10000,
                        'statut' => 'payee',
                    ],
                    [
                        'email' => 'rh@hrmanager.com',
                        'periode' => '2025-01',
                        'salaire_brut' => 390000,
                        'salaire_net' => 312000,
                        'heures_travaillees' => 158,
                        'heures_supplementaires' => 4,
                        'absences' => 1,
                        'montant_heures_sup' => 12000,
                        'prime_anciennete' => 30000,
                        'prime_productivite' => 10000,
                        'prime_autres' => 4000,
                        'total_cotisations' => 78000,
                        'total_retenues' => 9000,
                        'statut' => 'validee',
                    ],
                    [
                        'email' => 'manager@hrmanager.com',
                        'periode' => '2025-01',
                        'salaire_brut' => 360000,
                        'salaire_net' => 288000,
                        'heures_travaillees' => 156,
                        'heures_supplementaires' => 2,
                        'absences' => 0,
                        'montant_heures_sup' => 6000,
                        'prime_anciennete' => 25000,
                        'prime_productivite' => 8000,
                        'prime_autres' => 3000,
                        'total_cotisations' => 72000,
                        'total_retenues' => 7000,
                        'statut' => 'generee',
                    ],
                    [
                        'email' => 'employe@hrmanager.com',
                        'periode' => '2025-01',
                        'salaire_brut' => 240000,
                        'salaire_net' => 192000,
                        'heures_travaillees' => 151,
                        'heures_supplementaires' => 6,
                        'absences' => 2,
                        'montant_heures_sup' => 18000,
                        'prime_anciennete' => 10000,
                        'prime_productivite' => 6000,
                        'prime_autres' => 2000,
                        'total_cotisations' => 48000,
                        'total_retenues' => 6000,
                        'statut' => 'payee',
                    ],
                    [
                        'email' => 'employe@hrmanager.com',
                        'periode' => '2025-02',
                        'salaire_brut' => 248000,
                        'salaire_net' => 198400,
                        'heures_travaillees' => 159,
                        'heures_supplementaires' => 3,
                        'absences' => 0,
                        'montant_heures_sup' => 9000,
                        'prime_anciennete' => 10000,
                        'prime_productivite' => 7000,
                        'prime_autres' => 2500,
                        'total_cotisations' => 49600,
                        'total_retenues' => 6200,
                        'statut' => 'validee',
                    ],
                ];

                foreach ($fiches as $ficheData) {
                    $user = $users->get($ficheData['email']);

                    if (!$user) {
                        continue;
                    }

                    FichePaie::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'periode' => $ficheData['periode'],
                        ],
                        [
                            'date_emission' => now(),
                            'salaire_brut' => $ficheData['salaire_brut'],
                            'salaire_net' => $ficheData['salaire_net'],
                            'heures_travaillees' => $ficheData['heures_travaillees'],
                            'heures_supplementaires' => $ficheData['heures_supplementaires'],
                            'absences' => $ficheData['absences'],
                            'montant_heures_sup' => $ficheData['montant_heures_sup'],
                            'prime_anciennete' => $ficheData['prime_anciennete'],
                            'prime_productivite' => $ficheData['prime_productivite'],
                            'prime_autres' => $ficheData['prime_autres'],
                            'total_cotisations' => $ficheData['total_cotisations'],
                            'total_retenues' => $ficheData['total_retenues'],
                            'statut' => $ficheData['statut'],
                        ]
                    );
                }
            });
        } catch (\Exception $e) {
            Log::error('Erreur FichePaieSeeder: ' . $e->getMessage());
            throw $e;
        }
    }
}