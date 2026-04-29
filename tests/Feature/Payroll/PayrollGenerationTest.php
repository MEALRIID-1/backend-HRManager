<?php

namespace Tests\Feature\Payroll;

use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests Feature pour la génération des fiches de paie.
 */
class PayrollGenerationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $rh;
    private User $employe;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('payslips');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->rh = User::factory()->create();
        $this->rh->assignRole('rh');

        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');
    }

    /** @test */
    public function un_rh_peut_generer_une_fiche_de_paie(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 6,
                'annee' => 2024,
                'salaire_brut' => 3500.00,
                'heures_travaillees' => 151.67,
                'jours_travailles' => 21,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('fiches_paie', [
            'employe_id' => $this->employe->id,
            'mois' => 6,
            'annee' => 2024,
            'statut' => 'genere',
        ]);
    }

    /** @test */
    public function une_fiche_paie_ne_peut_pas_etre_dupliquee(): void
    {
        FichePaie::factory()->create([
            'employe_id' => $this->employe->id,
            'mois' => 6,
            'annee' => 2024,
            'statut' => 'genere',
        ]);

        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 6,
                'annee' => 2024,
                'salaire_brut' => 3500.00,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function un_employe_peut_voir_ses_propres_fiches_de_paie(): void
    {
        $fichePaie = FichePaie::factory()->create([
            'employe_id' => $this->employe->id,
            'statut' => 'envoye',
        ]);

        $response = $this->actingAs($this->employe)
            ->getJson('/api/payroll');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $fichePaie->id,
            ]);
    }

    /** @test */
    public function un_employe_ne_peut_pas_voir_les_fiches_d_un_autre(): void
    {
        $autreEmploye = User::factory()->create();
        FichePaie::factory()->create([
            'employe_id' => $autreEmploye->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->getJson('/api/payroll');

        $response->assertStatus(200)
            ->assertJsonMissing([
                'employe_id' => $autreEmploye->id,
            ]);
    }

    /** @test */
    public function un_rh_peut_generer_en_masse(): void
    {
        $employes = User::factory()->count(3)->create();
        foreach ($employes as $emp) {
            $emp->assignRole('employe');
        }

        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll/bulk', [
                'mois' => 6,
                'annee' => 2024,
                'employe_ids' => $employes->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'generees' => 3,
                ],
            ]);

        $this->assertDatabaseCount('fiches_paie', 3);
    }

    /** @test */
    public function le_salaire_brut_est_requis(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 6,
                'annee' => 2024,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['salaire_brut']);
    }

    /** @test */
    public function le_mois_doit_etre_valide(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 13,
                'annee' => 2024,
                'salaire_brut' => 3500.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mois']);
    }

    /** @test */
    public function un_employe_ne_peut_pas_generer_de_fiche_paie(): void
    {
        $response = $this->actingAs($this->employe)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 6,
                'annee' => 2024,
                'salaire_brut' => 3500.00,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function la_fiche_contient_les_cotisations_calculees(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/payroll', [
                'employe_id' => $this->employe->id,
                'mois' => 6,
                'annee' => 2024,
                'salaire_brut' => 3000.00,
            ]);

        $response->assertStatus(201);

        $fichePaie = FichePaie::where('employe_id', $this->employe->id)->first();

        $this->assertNotNull($fichePaie->cotisation_retraite);
        $this->assertNotNull($fichePaie->cotisation_securite_sociale);
        $this->assertNotNull($fichePaie->cotisation_chomage);
        $this->assertNotNull($fichePaie->salaire_net);

        // Vérifier que salaire_net < salaire_brut
        $this->assertLessThan($fichePaie->salaire_brut, $fichePaie->salaire_net);
    }
}
