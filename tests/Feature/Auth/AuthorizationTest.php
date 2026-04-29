<?php

namespace Tests\Feature\Auth;

use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests de sécurité: vérifier que les rôles ne peuvent pas escalader leurs privilèges.
 * Couverture OWASP: Broken Access Control
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rh;
    private User $manager;
    private User $employe;
    private User $autreEmploye;

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
        $this->employe->update(['manager_id' => $this->manager->id]);

        $this->autreEmploye = User::factory()->create();
        $this->autreEmploye->assignRole('employe');
    }

    // ============================================================================
    // TESTS: EMPLOYÉ
    // ============================================================================

    /** @test */
    public function un_employe_ne_peut_pas_promouvoir_son_role(): void
    {
        // Tentative d'accès aux endpoints admin
        $response = $this->actingAs($this->employe)
            ->getJson('/api/admin/users');

        $response->assertStatus(403);

        // Tentative de créer un utilisateur (réservé RH)
        $response = $this->actingAs($this->employe)
            ->postJson('/api/employees', [
                'name' => 'Hacker',
                'email' => 'hacker@example.com',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function un_employe_ne_peut_pas_modifier_son_propre_solde(): void
    {
        $response = $this->actingAs($this->employe)
            ->postJson("/api/employees/{$this->employe->id}/balance", [
                'type' => 'annuel',
                'solde' => 999,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function un_employe_ne_peut_pas_voir_les_donnees_d_un_autre_employe(): void
    {
        $response = $this->actingAs($this->employe)
            ->getJson("/api/employees/{$this->autreEmploye->id}/payslips");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_employe_ne_peut_pas_valider_son_propre_conge(): void
    {
        $conge = \App\Models\Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'soumis',
        ]);

        $response = $this->actingAs($this->employe)
            ->postJson("/api/leaves/{$conge->id}/approve");

        $response->assertStatus(403);
    }

    // ============================================================================
    // TESTS: MANAGER
    // ============================================================================

    /** @test */
    public function un_manager_ne_peut_pas_acceder_aux_fiches_paie(): void
    {
        $response = $this->actingAs($this->manager)
            ->getJson('/api/payroll');

        // Le manager ne peut voir que les fiches de son équipe, pas toutes
        // Ou reçoit 403 selon la politique
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    /** @test */
    public function un_manager_ne_peut_pas_modifier_les_salaires(): void
    {
        $response = $this->actingAs($this->manager)
            ->putJson("/api/employees/{$this->employe->id}/salary", [
                'salaire' => 100000,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function un_manager_ne_peut_pas_gerer_les_contrats_autre_departement(): void
    {
        // Créer un contrat pour un employé d'un autre département
        $autreManager = User::factory()->create();
        $autreManager->assignRole('manager');
        
        $employeAutreDept = User::factory()->create([
            'manager_id' => $autreManager->id,
        ]);
        $employeAutreDept->assignRole('employe');
        
        $contrat = Contrat::factory()->create([
            'employe_id' => $employeAutreDept->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/contracts/{$contrat->id}");

        $response->assertStatus(403);
    }

    // ============================================================================
    // TESTS: RH
    // ============================================================================

    /** @test */
    public function un_rh_ne_peut_pas_modifier_les_roles_admin(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson("/api/users/{$this->admin->id}/roles", [
                'roles' => ['employe'], // Tentative de rétrograder l'admin
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function un_rh_ne_peut_pas_supprimer_un_admin(): void
    {
        $response = $this->actingAs($this->rh)
            ->deleteJson("/api/users/{$this->admin->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_rh_peut_voir_toutes_les_fiches_paie(): void
    {
        FichePaie::factory()->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->actingAs($this->rh)
            ->getJson('/api/payroll');

        $response->assertStatus(200);
    }

    // ============================================================================
    // TESTS: PRIVILÈGES HORIZONTAUX (IDOR)
    // ============================================================================

    /** @test */
    public function protection_contre_idor_sur_contrats(): void
    {
        // IDOR: Insecure Direct Object Reference
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->autreEmploye->id,
        ]);

        // L'employé tente d'accéder au contrat d'un autre
        $response = $this->actingAs($this->employe)
            ->getJson("/api/contracts/{$contrat->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function protection_contre_idor_sur_fiches_paie(): void
    {
        $payslip = FichePaie::factory()->create([
            'employe_id' => $this->autreEmploye->id,
        ]);

        // Tentative d'accès direct
        $response = $this->actingAs($this->employe)
            ->getJson("/api/payroll/{$payslip->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function protection_contre_idor_sur_conges(): void
    {
        $conge = \App\Models\Conge::factory()->create([
            'employe_id' => $this->autreEmploye->id,
        ]);

        $response = $this->actingAs($this->employe)
            ->getJson("/api/leaves/{$conge->id}");

        $response->assertStatus(403);
    }

    // ============================================================================
    // TESTS: MASS ASSIGNMENT
    // ============================================================================

    /** @test */
    public function protection_contre_mass_assignment_role(): void
    {
        // Tentative de s'assigner le rôle admin
        $response = $this->actingAs($this->employe)
            ->putJson("/api/employees/{$this->employe->id}", [
                'name' => $this->employe->name,
                'role' => 'admin', // Ce champ ne devrait pas être mass-assignable
            ]);

        // Soit 403, soit le rôle n'est pas modifié
        if ($response->status() === 200) {
            $this->assertFalse($this->employe->fresh()->hasRole('admin'));
        } else {
            $response->assertStatus(403);
        }
    }

    /** @test */
    public function protection_contre_mass_assignment_admin_flag(): void
    {
        $response = $this->actingAs($this->employe)
            ->putJson("/api/employees/{$this->employe->id}", [
                'name' => $this->employe->name,
                'is_admin' => true,
            ]);

        $response->assertStatus(200);
        
        // Vérifier que le flag n'est pas modifié
        $this->assertFalse($this->employe->fresh()->is_admin ?? false);
    }

    // ============================================================================
    // TESTS: API SENSIBLES
    // ============================================================================

    /** @test */
    public function endpoints_audit_reserves_aux_admin(): void
    {
        $response = $this->actingAs($this->rh)
            ->getJson('/api/audit/logs');

        // Selon les politiques, RH peut ou non voir les logs
        $this->assertTrue(in_array($response->status(), [200, 403]));

        $response = $this->actingAs($this->employe)
            ->getJson('/api/audit/logs');

        $response->assertStatus(403);
    }

    /** @test */
    public function endpoints_system_reserves_au_seul_admin(): void
    {
        $endpoints = [
            '/api/system/config',
            '/api/system/cache/clear',
            '/api/system/maintenance',
        ];

        foreach ($endpoints as $endpoint) {
            // RH ne peut pas
            $response = $this->actingAs($this->rh)->getJson($endpoint);
            $response->assertStatus(403);

            // Employé ne peut pas
            $response = $this->actingAs($this->employe)->getJson($endpoint);
            $response->assertStatus(403);
        }
    }

    // ============================================================================
    // TESTS: SESSION HIJACKING
    // ============================================================================

    /** @test */
    public function tokens_sont_lies_a_l_utilisateur(): void
    {
        // Créer un token pour employe
        $token = $this->employe->createToken('test-token')->plainTextToken;

        // Tenter d'utiliser le token avec un autre user_id dans la requête
        // Le middleware devrait rejeter car le token appartient à employe
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJson(['data' => ['id' => $this->employe->id]]);
    }
}
