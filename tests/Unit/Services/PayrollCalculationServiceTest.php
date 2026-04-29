<?php

namespace Tests\Unit\Services;

use App\Models\FichePaie;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests unitaires pour le calcul des fiches de paie.
 */
class PayrollCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PayrollCalculationService();
    }

    /** @test */
    public function le_calcul_des_cotisations_est_correct(): void
    {
        $salaireBrut = 3000.00;

        $cotisations = $this->service->calculerCotisations($salaireBrut);

        // Vérifier que toutes les cotisations sont présentes
        $this->assertArrayHasKey('retraite', $cotisations);
        $this->assertArrayHasKey('securite_sociale', $cotisations);
        $this->assertArrayHasKey('chomage', $cotisations);
        $this->assertArrayHasKey('csg_crd', $cotisations);

        // Vérifier les valeurs approximatives
        $this->assertEqualsWithDelta(210.00, $cotisations['retraite'], 0.01); // 7%
        $this->assertEqualsWithDelta(390.00, $cotisations['securite_sociale'], 0.01); // 13%
        $this->assertEqualsWithDelta(60.00, $cotisations['chomage'], 0.01); // 2%
    }

    /** @test */
    public function le_salaire_net_est_correctement_calcule(): void
    {
        $salaireBrut = 3000.00;
        $cotisations = [
            'retraite' => 210.00,
            'securite_sociale' => 390.00,
            'chomage' => 60.00,
            'csg_crd' => 294.00,
        ];

        $salaireNet = $this->service->calculerSalaireNet($salaireBrut, $cotisations);

        $totalCotisations = array_sum($cotisations);
        $this->assertEquals($salaireBrut - $totalCotisations, $salaireNet);
    }

    /** @test */
    public function la_generation_de_fiche_paie_fonctionne(): void
    {
        $employe = User::factory()->create();
        $employe->assignRole('employe');

        $data = [
            'employe_id' => $employe->id,
            'mois' => 6,
            'annee' => 2024,
            'salaire_brut' => 3500.00,
            'heures_travaillees' => 151.67,
            'jours_travailles' => 21,
        ];

        $fichePaie = $this->service->genererFichePaie($data);

        $this->assertInstanceOf(FichePaie::class, $fichePaie);
        $this->assertEquals(3500.00, $fichePaie->salaire_brut);
        $this->assertNotNull($fichePaie->salaire_net);
        $this->assertNotNull($fichePaie->cotisation_retraite);

        $this->assertDatabaseHas('fiches_paie', [
            'employe_id' => $employe->id,
            'mois' => 6,
            'annee' => 2024,
        ]);
    }

    /** @test */
    public function une_fiche_paie_existante_ne_peut_pas_etre_regenerer(): void
    {
        $employe = User::factory()->create();
        FichePaie::factory()->create([
            'employe_id' => $employe->id,
            'mois' => 6,
            'annee' => 2024,
            'statut' => 'genere',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('existe déjà');

        $this->service->genererFichePaie([
            'employe_id' => $employe->id,
            'mois' => 6,
            'annee' => 2024,
            'salaire_brut' => 3500.00,
        ]);
    }

    /** @test */
    public function le_calcul_du_prorata_est_correct(): void
    {
        $salaireBase = 3000.00;
        $joursTravailles = 15;
        $joursOuvres = 21;

        $salaireProrata = $this->service->calculerProrata(
            $salaireBase,
            $joursTravailles,
            $joursOuvres
        );

        $expected = ($salaireBase / $joursOuvres) * $joursTravailles;
        $this->assertEqualsWithDelta($expected, $salaireProrata, 0.01);
    }

    /** @test */
    public function la_generation_en_masse_fonctionne(): void
    {
        $employes = User::factory()->count(3)->create();
        foreach ($employes as $employe) {
            $employe->assignRole('employe');
        }

        $result = $this->service->genererEnMasse(
            mois: 6,
            annee: 2024,
            employeIds: $employes->pluck('id')->toArray()
        );

        $this->assertCount(3, $result['generees']);
        $this->assertEquals(0, $result['erreurs']);

        $this->assertDatabaseCount('fiches_paie', 3);
    }
}
