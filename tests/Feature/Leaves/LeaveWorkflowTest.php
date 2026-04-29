<?php

namespace Tests\Feature\Leaves;

use App\Models\Conge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests Feature pour le workflow des congés bout en bout.
 */
class LeaveWorkflowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $employe;
    private User $manager;
    private User $rh;
    private User $directeur;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->rh = User::factory()->create();
        $this->rh->assignRole('rh');

        $this->directeur = User::factory()->create();
        $this->directeur->assignRole('directeur');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        // Assigner le manager
        $this->employe->update(['manager_id' => $this->manager->id]);

        // Créer le solde de congés
        $this->employe->soldesConges()->create([
            'type' => Conge::TYPE_ANNUEL,
            'solde' => 25,
            'annee' => now()->year,
        ]);
    }

    /** @test */
    public function workflow_complet_bout_en_bout(): void
    {
        // 1. L'employé crée un brouillon de congé
        $response = $this->actingAs($this->employe)
            ->postJson('/api/leaves', [
                'type' => Conge::TYPE_ANNUEL,
                'date_debut' => now()->addWeek()->format('Y-m-d'),
                'date_fin' => now()->addWeek()->addDays(4)->format('Y-m-d'),
                'motif' => 'Vacances',
            ]);

        $response->assertStatus(201);
        $congeId = $response->json('data.id');

        $this->assertDatabaseHas('conges', [
            'id' => $congeId,
            'etat' => Conge::ETAT_BROUILLON,
        ]);

        // 2. L'employé soumet sa demande
        $response = $this->actingAs($this->employe)
            ->postJson("/api/leaves/{$congeId}/submit");

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $congeId,
            'etat' => Conge::ETAT_SOUMIS,
        ]);

        // 3. Le manager valide
        $response = $this->actingAs($this->manager)
            ->postJson("/api/leaves/{$congeId}/approve", [
                'commentaire' => 'OK pour moi',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $congeId,
            'etat' => Conge::ETAT_VALIDE_MANAGER,
        ]);

        // 4. Le RH valide
        $response = $this->actingAs($this->rh)
            ->postJson("/api/leaves/{$congeId}/approve", [
                'commentaire' => 'Solde OK',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $congeId,
            'etat' => Conge::ETAT_VALIDE_RH,
        ]);

        // 5. Le Directeur approuve
        $response = $this->actingAs($this->directeur)
            ->postJson("/api/leaves/{$congeId}/approve", [
                'commentaire' => 'Approuvé',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $congeId,
            'etat' => Conge::ETAT_APPROUVE,
        ]);
    }

    /** @test */
    public function le_manager_peut_refuser_un_conge(): void
    {
        $conge = Conge::factory()->soumis()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/leaves/{$conge->id}/reject", [
                'motif' => 'Période critique',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $conge->id,
            'etat' => Conge::ETAT_REFUSE_MANAGER,
        ]);
    }

    /** @test */
    public function un_manager_ne_peut_pas_approuver_son_propre_conge(): void
    {
        $conge = Conge::factory()->soumis()->create([
            'employe_id' => $this->manager->id,
            'etat' => Conge::ETAT_SOUMIS,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/leaves/{$conge->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function l_employe_ne_peut_pas_soumettre_sans_solde_suffisant(): void
    {
        // Épuiser le solde
        Conge::factory()->count(5)->create([
            'employe_id' => $this->employe->id,
            'type' => Conge::TYPE_ANNUEL,
            'etat' => Conge::ETAT_APPROUVE,
            'nombre_jours' => 5,
        ]);

        $response = $this->actingAs($this->employe)
            ->postJson('/api/leaves', [
                'type' => Conge::TYPE_ANNUEL,
                'date_debut' => now()->addWeek()->format('Y-m-d'),
                'date_fin' => now()->addWeek()->addDays(4)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['no_overlap']);
    }

    /** @test */
    public function deux_conges_ne_peuvent_pas_se_chevaucher(): void
    {
        Conge::factory()->approuve()->create([
            'employe_id' => $this->employe->id,
            'date_debut' => now()->addWeek(),
            'date_fin' => now()->addWeek()->addDays(4),
        ]);

        $response = $this->actingAs($this->employe)
            ->postJson('/api/leaves', [
                'type' => Conge::TYPE_ANNUEL,
                'date_debut' => now()->addWeek()->addDays(2)->format('Y-m-d'),
                'date_fin' => now()->addWeek()->addDays(6)->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['no_overlap']);
    }

    /** @test */
    public function l_employe_peut_annuler_son_conge_soumis(): void
    {
        $conge = Conge::factory()->soumis()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->postJson("/api/leaves/{$conge->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('conges', [
            'id' => $conge->id,
            'etat' => Conge::ETAT_ANNULE,
        ]);
    }

    /** @test */
    public function l_employe_ne_peut_pas_annuler_un_conge_approuve(): void
    {
        $conge = Conge::factory()->approuve()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->postJson("/api/leaves/{$conge->id}/cancel");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_autre_manager_ne_peut_pas_valider(): void
    {
        $autreManager = User::factory()->create();
        $autreManager->assignRole('manager');

        $conge = Conge::factory()->soumis()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($autreManager)
            ->postJson("/api/leaves/{$conge->id}/approve");

        $response->assertStatus(403);
    }

    /** @test */
    public function le_refus_exige_un_motif(): void
    {
        $conge = Conge::factory()->soumis()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/leaves/{$conge->id}/reject", [
                'motif' => 'ab', // Trop court
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['motif']);
    }

    /** @test */
    public function l_admin_peut_voir_tous_les_conges(): void
    {
        Conge::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/leaves');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data.data');
    }

    /** @test */
    public function le_manager_voit_seulement_les_conges_a_valider(): void
    {
        Conge::factory()->soumis()->create([
            'employe_id' => $this->employe->id,
        ]);

        Conge::factory()->soumis()->create([
            'employe_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/leaves/pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
