<?php

namespace Tests\Unit\Services;

use App\Models\Conge;
use App\Models\User;
use App\Modules\Leaves\Services\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests unitaires pour le service LeaveWorkflowService.
 */
class LeaveWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveWorkflowService $service;
    private User $employe;
    private User $manager;
    private User $rh;
    private User $directeur;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->service = new LeaveWorkflowService();

        // Créer les utilisateurs avec leurs rôles
        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->rh = User::factory()->create();
        $this->rh->assignRole('rh');

        $this->directeur = User::factory()->create();
        $this->directeur->assignRole('directeur');

        // Assigner le manager à l'employé
        $this->employe->update(['manager_id' => $this->manager->id]);
    }

    /** @test */
    public function un_employe_peut_soumettre_son_conge(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_BROUILLON,
        ]);

        $result = $this->service->soumettreConge($conge, $this->employe->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(Conge::ETAT_SOUMIS, $conge->fresh()->etat);
        $this->assertNotNull($conge->fresh()->date_soumission);
    }

    /** @test */
    public function un_manager_peut_approuver_un_conge_de_son_equipe(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_SOUMIS,
        ]);

        $result = $this->service->validerConge($conge, $this->manager, 'manager');

        $this->assertTrue($result['success']);
        $this->assertEquals(Conge::ETAT_VALIDE_MANAGER, $conge->fresh()->etat);
    }

    /** @test */
    public function un_manager_ne_peut_pas_approuver_un_conge_qui_n_est_pas_soumis(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_BROUILLON,
        ]);

        $result = $this->service->validerConge($conge, $this->manager, 'manager');

        $this->assertFalse($result['success']);
        $this->assertEquals(Conge::ETAT_BROUILLON, $conge->fresh()->etat);
    }

    /** @test */
    public function le_workflow_complet_passe_par_tous_les_niveaux(): void
    {
        // 1. Soumission par l'employé
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_BROUILLON,
        ]);

        $this->service->soumettreConge($conge, $this->employe->id);
        $this->assertEquals(Conge::ETAT_SOUMIS, $conge->fresh()->etat);

        // 2. Validation manager
        $this->service->validerConge($conge, $this->manager, 'manager');
        $this->assertEquals(Conge::ETAT_VALIDE_MANAGER, $conge->fresh()->etat);

        // 3. Validation RH
        $this->service->validerConge($conge, $this->rh, 'rh');
        $this->assertEquals(Conge::ETAT_VALIDE_RH, $conge->fresh()->etat);

        // 4. Approbation finale directeur
        $this->service->validerConge($conge, $this->directeur, 'directeur');
        $this->assertEquals(Conge::ETAT_APPROUVE, $conge->fresh()->etat);
    }

    /** @test */
    public function un_manager_ne_peut_pas_approuver_un_conge_d_un_autre_equipe(): void
    {
        $autreManager = User::factory()->create();
        $autreManager->assignRole('manager');

        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_SOUMIS,
        ]);

        $result = $this->service->validerConge($conge, $autreManager, 'manager');

        $this->assertFalse($result['success']);
        $this->assertEquals(Conge::ETAT_SOUMIS, $conge->fresh()->etat);
    }

    /** @test */
    public function le_solde_de_conges_est_correctement_calcule(): void
    {
        // Créer des congés approuvés
        Conge::factory()->count(3)->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_APPROUVE,
            'type' => Conge::TYPE_ANNUEL,
            'nombre_jours' => 5,
        ]);

        $solde = $this->service->calculerSoldeConges($this->employe->id, Conge::TYPE_ANNUEL);

        $this->assertEquals(15, $solde['jours_pris']);
        $this->assertArrayHasKey('solde_restant', $solde);
    }

    /** @test */
    public function un_conge_annule_est_mis_a_jour_correctement(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_SOUMIS,
        ]);

        $result = $this->service->annulerConge($conge, $this->employe->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(Conge::ETAT_ANNULE, $conge->fresh()->etat);
    }

    /** @test */
    public function un_employe_ne_peut_pas_annuler_un_conge_approuve(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => Conge::ETAT_APPROUVE,
        ]);

        $result = $this->service->annulerConge($conge, $this->employe->id);

        $this->assertFalse($result['success']);
    }
}
