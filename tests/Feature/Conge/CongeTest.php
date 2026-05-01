<?php

declare(strict_types=1);

namespace Tests\Feature\Conge;

use App\Models\Conge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CongeTest extends TestCase
{
    use RefreshDatabase;

    private User $employeUser;
    private User $managerUser;
    private User $rhUser;
    private User $adminUser;
    private string $employeToken;
    private string $managerToken;
    private string $rhToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles
        $adminRole = Role::factory()->create(['slug' => 'admin', 'nom' => 'Administrateur', 'niveau_validation' => 3]);
        $rhRole = Role::factory()->create(['slug' => 'rh', 'nom' => 'Ressources Humaines', 'niveau_validation' => 2]);
        $managerRole = Role::factory()->create(['slug' => 'manager', 'nom' => 'Manager', 'niveau_validation' => 1]);
        $employeRole = Role::factory()->create(['slug' => 'employe', 'nom' => 'Employé', 'niveau_validation' => 0]);

        // Créer les utilisateurs
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true, 'departement' => 'IT']);
        $this->adminUser->roles()->attach($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

        $this->rhUser = User::factory()->create(['email' => 'rh@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true, 'departement' => 'RH']);
        $this->rhUser->roles()->attach($rhRole);
        $this->rhToken = $this->rhUser->createToken('test-token')->plainTextToken;

        $this->managerUser = User::factory()->create(['email' => 'manager@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true, 'departement' => 'Commercial']);
        $this->managerUser->roles()->attach($managerRole);
        $this->managerToken = $this->managerUser->createToken('test-token')->plainTextToken;

        $this->employeUser = User::factory()->create(['email' => 'employe@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true, 'departement' => 'Commercial']);
        $this->employeUser->roles()->attach($employeRole);
        $this->employeToken = $this->employeUser->createToken('test-token')->plainTextToken;
    }

    /**
     * Test: Employé peut déposer une demande de congé.
     */
    public function test_employe_peut_deposer_demande_conge(): void
    {
        $congeData = [
            'type' => 'conge_paye',
            'date_debut' => now()->addDays(5)->format('Y-m-d'),
            'date_fin' => now()->addDays(10)->format('Y-m-d'),
            'commentaire' => 'Vacances d\'été',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->postJson('/api/v1/conges', $congeData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Demande de congé créée avec succès',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'date_debut',
                    'date_fin',
                    'etat',
                    'employe_id',
                ],
            ]);

        $this->assertDatabaseHas('conges', [
            'employe_id' => $this->employeUser->id,
            'type' => 'conge_paye',
            'etat' => 'en_attente',
            'niveau_validation' => 0,
        ]);
    }

    /**
     * Test: Manager valide au niveau 1.
     */
    public function test_manager_valide_niveau_1(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->managerToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider', [
                'commentaire' => 'Validé par le manager',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé validé',
            ]);

        $conge->refresh();
        $this->assertEquals('partiellement_valide', $conge->etat);
        $this->assertEquals(1, $conge->niveau_validation);
    }

    /**
     * Test: RH valide au niveau 2.
     */
    public function test_rh_valide_niveau_2(): void
    {
        // Créer un congé déjà validé niveau 1
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'partiellement_valide',
            'niveau_validation' => 1,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider', [
                'commentaire' => 'Validé par la RH',
            ]);

        $response->assertStatus(200);

        $conge->refresh();
        $this->assertEquals('partiellement_valide', $conge->etat);
        $this->assertEquals(2, $conge->niveau_validation);
    }

    /**
     * Test: Validation simultanée N1 et N2 (RH peut valider directement).
     */
    public function test_validation_simultanee_n1_et_n2(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        // Le RH (N2) peut valider directement, sautant N1
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider', [
                'commentaire' => 'Validé directement par RH',
            ]);

        $response->assertStatus(200);

        $conge->refresh();
        // Selon l'implémentation, cela pourrait passer directement à approuvé
        // ou rester partiellement_valide niveau 2
        $this->assertGreaterThanOrEqual(2, $conge->niveau_validation);
    }

    /**
     * Test: Admin peut faire une super validation (bypass workflow).
     */
    public function test_admin_super_validation_bypass_workflow(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        // L'admin valide directement
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider', [
                'commentaire' => 'Super validation admin',
            ]);

        $response->assertStatus(200);

        $conge->refresh();
        $this->assertEquals('approuve', $conge->etat);
        $this->assertEquals(3, $conge->niveau_validation);
    }

    /**
     * Test: Refus d'un congé nécessite un motif.
     */
    public function test_refus_conge_necessite_motif(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        // Tentative de refus sans motif
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->managerToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/refuser');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['motif_refus']);

        // Refus avec motif
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->managerToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/refuser', [
                'motif_refus' => 'Manque de personnel sur cette période',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé refusé',
            ]);

        $conge->refresh();
        $this->assertEquals('refuse', $conge->etat);
        $this->assertEquals('Manque de personnel sur cette période', $conge->motif_refus);
    }

    /**
     * Test: Congé approuvé quand tous les niveaux sont validés.
     */
    public function test_conge_approuve_quand_tous_niveaux_valides(): void
    {
        // Créer un congé au niveau 2 (validé par RH)
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'partiellement_valide',
            'niveau_validation' => 2,
            'date_debut' => now()->addDays(5),
            'date_fin' => now()->addDays(10),
        ]);

        // L'admin valide (dernier niveau)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider');

        $response->assertStatus(200);

        $conge->refresh();
        $this->assertEquals('approuve', $conge->etat);
        $this->assertEquals(3, $conge->niveau_validation);
    }

    /**
     * Test: Employé ne peut pas valider son propre congé.
     */
    public function test_employe_ne_peut_pas_valider_son_propre_conge(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider');

        $response->assertStatus(403);
    }

    /**
     * Test: Validation d'un congé déjà refusé échoue.
     */
    public function test_validation_conge_deja_refuse_echoue(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'refuse',
            'motif_refus' => 'Déjà refusé',
            'niveau_validation' => 0,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/conges/' . $conge->id . '/valider');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Ce congé a déjà été traité',
            ]);
    }

    /**
     * Test: Employé peut voir ses propres congés.
     */
    public function test_employe_peut_voir_ses_conges(): void
    {
        Conge::factory()->create([
            'employe_id' => $this->employeUser->id,
            'type' => 'conge_paye',
            'etat' => 'en_attente',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->getJson('/api/v1/conges/mes-conges');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
                'meta',
            ]);
    }

    /**
     * Test: Validation avec dates invalides.
     */
    public function test_validation_dates_invalides(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->postJson('/api/v1/conges', [
                'type' => 'conge_paye',
                'date_debut' => now()->addDays(10)->format('Y-m-d'),
                'date_fin' => now()->addDays(5)->format('Y-m-d'), // Date fin avant début
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_fin']);
    }
}
