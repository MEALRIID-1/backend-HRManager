<?php

namespace Tests\Feature\Contracts;

use App\Models\Contrat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Tests Feature pour le cycle de vie des contrats.
 */
class ContractLifecycleTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $rh;
    private User $manager;
    private User $employe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->rh = User::factory()->create();
        $this->rh->assignRole('rh');

        $this->manager = User::factory()->create();
        $this->manager->assignRole('manager');

        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');
    }

    /** @test */
    public function un_rh_peut_creer_un_contrat_cdi(): void
    {
        $contratData = [
            'employe_id' => $this->employe->id,
            'type' => 'cdi',
            'date_debut' => now()->format('Y-m-d'),
            'salaire_base' => 3000.00,
            'fonction' => 'Développeur',
        ];

        $response = $this->actingAs($this->rh)
            ->postJson('/api/contracts', $contratData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $this->employe->id,
            'type' => 'cdi',
            'etat' => 'actif',
        ]);
    }

    /** @test */
    public function un_cdi_ne_peut_pas_avoir_de_date_fin(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/contracts', [
                'employe_id' => $this->employe->id,
                'type' => 'cdi',
                'date_debut' => now()->format('Y-m-d'),
                'date_fin' => now()->addYear()->format('Y-m-d'),
                'salaire_base' => 3000.00,
            ]);

        // La date_fin devrait être ignorée pour les CDI
        $response->assertStatus(201);

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $this->employe->id,
            'date_fin' => null,
        ]);
    }

    /** @test */
    public function un_cdd_doit_avoir_une_date_fin(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/contracts', [
                'employe_id' => $this->employe->id,
                'type' => 'cdd',
                'date_debut' => now()->format('Y-m-d'),
                'salaire_base' => 3000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_fin']);
    }

    /** @test */
    public function le_salaire_doit_etre_superieur_au_smig(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/contracts', [
                'employe_id' => $this->employe->id,
                'type' => 'cdi',
                'date_debut' => now()->format('Y-m-d'),
                'salaire_base' => 1000.00, // Inférieur au SMIG
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['salaire_base']);
    }

    /** @test */
    public function deux_contrats_ne_peuvent_pas_se_chevaucher(): void
    {
        // Créer un contrat existant actif
        Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'date_debut' => now(),
            'date_fin' => now()->addMonths(6),
            'etat' => 'actif',
        ]);

        $response = $this->actingAs($this->rh)
            ->postJson('/api/contracts', [
                'employe_id' => $this->employe->id,
                'type' => 'cdd',
                'date_debut' => now()->addMonth()->format('Y-m-d'),
                'date_fin' => now()->addMonths(3)->format('Y-m-d'),
                'salaire_base' => 3000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['no_overlap']);
    }

    /** @test */
    public function un_rh_peut_terminer_un_contrat(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'actif',
        ]);

        $response = $this->actingAs($this->rh)
            ->postJson("/api/contracts/{$contrat->id}/terminate", [
                'date_terminaison' => now()->format('Y-m-d'),
                'motif_terminaison' => 'Démission',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('contrats', [
            'id' => $contrat->id,
            'etat' => 'termine',
        ]);
    }

    /** @test */
    public function un_employe_ne_peut_pas_creer_de_contrat(): void
    {
        $response = $this->actingAs($this->employe)
            ->postJson('/api/contracts', [
                'employe_id' => $this->employe->id,
                'type' => 'cdi',
                'date_debut' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function un_employe_peut_voir_ses_propres_contrats(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->getJson("/api/contracts/{$contrat->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function un_employe_ne_peut_pas_voir_les_contrats_d_un_autre(): void
    {
        $autreEmploye = User::factory()->create();
        $contrat = Contrat::factory()->create([
            'employe_id' => $autreEmploye->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->getJson("/api/contracts/{$contrat->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_manager_peut_voir_les_contrats_de_son_equipe(): void
    {
        $this->employe->update(['manager_id' => $this->manager->id]);

        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/contracts/{$contrat->id}");

        $response->assertStatus(200);
    }
}
