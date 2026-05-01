<?php

declare(strict_types=1);

namespace Tests\Feature\Contrat;

use App\Models\Contrat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ContratTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $rhUser;
    private User $managerUser;
    private User $employeUser;
    private string $adminToken;
    private string $rhToken;
    private string $managerToken;
    private string $employeToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles
        $adminRole = Role::factory()->create(['slug' => 'admin', 'nom' => 'Administrateur']);
        $rhRole = Role::factory()->create(['slug' => 'rh', 'nom' => 'Ressources Humaines']);
        $managerRole = Role::factory()->create(['slug' => 'manager', 'nom' => 'Manager']);
        $employeRole = Role::factory()->create(['slug' => 'employe', 'nom' => 'Employé']);

        // Créer les permissions
        Permission::factory()->create(['slug' => 'contrats.creer', 'nom' => 'Créer contrats', 'module' => 'contrats']);
        Permission::factory()->create(['slug' => 'contrats.voir', 'nom' => 'Voir contrats', 'module' => 'contrats']);
        Permission::factory()->create(['slug' => 'contrats.imprimer', 'nom' => 'Imprimer contrats', 'module' => 'contrats']);

        // Assigner permissions aux rôles
        $adminRole->permissions()->attach(Permission::all());
        $rhRole->permissions()->attach(Permission::where('slug', 'contrats.creer')->first());
        $rhRole->permissions()->attach(Permission::where('slug', 'contrats.voir')->first());
        $rhRole->permissions()->attach(Permission::where('slug', 'contrats.imprimer')->first());
        $employeRole->permissions()->attach(Permission::where('slug', 'contrats.voir')->first());

        // Créer les utilisateurs
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true]);
        $this->adminUser->roles()->attach($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

        $this->rhUser = User::factory()->create(['email' => 'rh@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true]);
        $this->rhUser->roles()->attach($rhRole);
        $this->rhToken = $this->rhUser->createToken('test-token')->plainTextToken;

        $this->managerUser = User::factory()->create(['email' => 'manager@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true]);
        $this->managerUser->roles()->attach($managerRole);
        $this->managerToken = $this->managerUser->createToken('test-token')->plainTextToken;

        $this->employeUser = User::factory()->create(['email' => 'employe@test.com', 'password' => Hash::make('Password123!'), 'is_active' => true]);
        $this->employeUser->roles()->attach($employeRole);
        $this->employeToken = $this->employeUser->createToken('test-token')->plainTextToken;
    }

    /**
     * Test: RH peut créer un contrat.
     */
    public function test_rh_peut_creer_contrat(): void
    {
        $contratData = [
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'date_debut' => now()->format('Y-m-d'),
            'date_fin' => null,
            'salaire_base' => 3500.00,
            'poste' => 'Développeur Senior',
            'departement' => 'IT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', $contratData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Contrat créé avec succès',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'date_debut',
                    'salaire_base',
                    'etat',
                ],
            ]);

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'etat' => 'actif',
            'salaire_base' => 3500.00,
        ]);
    }

    /**
     * Test: Un seul contrat actif par employé.
     */
    public function test_un_seul_contrat_actif_par_employe(): void
    {
        // Créer un premier contrat actif
        Contrat::factory()->create([
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'etat' => 'actif',
            'date_debut' => now()->subMonth(),
        ]);

        // Tentative de créer un deuxième contrat actif
        $contratData = [
            'employe_id' => $this->employeUser->id,
            'type' => 'CDD',
            'date_debut' => now()->format('Y-m-d'),
            'date_fin' => now()->addYear()->format('Y-m-d'),
            'salaire_base' => 3000.00,
            'poste' => 'Développeur',
            'departement' => 'IT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', $contratData);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Un contrat actif existe déjà pour cet employé',
            ]);

        // Vérifier qu'il n'y a qu'un seul contrat actif
        $this->assertEquals(1, Contrat::where('employe_id', $this->employeUser->id)
            ->where('etat', 'actif')
            ->count());
    }

    /**
     * Test: Employé ne peut pas imprimer de contrat.
     */
    public function test_employe_ne_peut_pas_imprimer_contrat(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'etat' => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->postJson('/api/v1/contrats/' . $contrat->id . '/imprimer');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Accès interdit',
            ]);
    }

    /**
     * Test: RH peut imprimer un contrat.
     */
    public function test_rh_peut_imprimer_contrat(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'etat' => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats/' . $contrat->id . '/imprimer');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PDF généré avec succès',
            ]);
    }

    /**
     * Test: Manager ne peut pas créer de contrat (sans permission).
     */
    public function test_manager_ne_peut_pas_creer_contrat(): void
    {
        $contratData = [
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'date_debut' => now()->format('Y-m-d'),
            'salaire_base' => 3500.00,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->managerToken)
            ->postJson('/api/v1/contrats', $contratData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Accès interdit',
            ]);
    }

    /**
     * Test: Employé peut voir son propre contrat.
     */
    public function test_employe_peut_voir_son_contrat(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employeUser->id,
            'type' => 'CDI',
            'etat' => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->getJson('/api/v1/contrats/' . $contrat->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.id', $contrat->id);
    }

    /**
     * Test: Employé ne peut pas voir le contrat d'un autre employé.
     */
    public function test_employe_ne_peut_pas_voir_autre_contrat(): void
    {
        $autreEmploye = User::factory()->create(['email' => 'autre@test.com']);
        $contrat = Contrat::factory()->create([
            'employe_id' => $autreEmploye->id,
            'type' => 'CDI',
            'etat' => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->getJson('/api/v1/contrats/' . $contrat->id);

        $response->assertStatus(403);
    }

    /**
     * Test: Validation des données lors de la création d'un contrat.
     */
    public function test_validation_creation_contrat(): void
    {
        // Type de contrat invalide
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', [
                'employe_id' => $this->employeUser->id,
                'type' => 'INVALID_TYPE',
                'date_debut' => now()->format('Y-m-d'),
                'salaire_base' => 3500.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // Employe_id manquant
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', [
                'type' => 'CDI',
                'date_debut' => now()->format('Y-m-d'),
                'salaire_base' => 3500.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employe_id']);
    }

    /**
     * Test: Création d'un contrat avec date de fin pour CDD.
     */
    public function test_contrat_cdd_necessite_date_fin(): void
    {
        // CDD sans date de fin
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', [
                'employe_id' => $this->employeUser->id,
                'type' => 'CDD',
                'date_debut' => now()->format('Y-m-d'),
                'salaire_base' => 3000.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_fin']);

        // CDD avec date de fin
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->postJson('/api/v1/contrats', [
                'employe_id' => $this->employeUser->id,
                'type' => 'CDD',
                'date_debut' => now()->format('Y-m-d'),
                'date_fin' => now()->addMonths(6)->format('Y-m-d'),
                'salaire_base' => 3000.00,
                'poste' => 'Développeur',
                'departement' => 'IT',
            ]);

        $response->assertStatus(201);

        // Terminer le contrat CDI précédent pour créer le CDD
        Contrat::where('employe_id', $this->employeUser->id)->update(['etat' => 'termine']);
    }

    /**
     * Test: Liste des contrats accessible par RH.
     */
    public function test_rh_peut_lister_contrats(): void
    {
        Contrat::factory()->count(5)->create(['etat' => 'actif']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->rhToken)
            ->getJson('/api/v1/contrats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'date_debut',
                        'etat',
                        'employe',
                    ],
                ],
                'meta',
            ]);
    }

    /**
     * Test: Soft delete d'un contrat.
     */
    public function test_soft_delete_contrat(): void
    {
        $contrat = Contrat::factory()->create([
            'employe_id' => $this->employeUser->id,
            'etat' => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/contrats/' . $contrat->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Contrat supprimé avec succès',
            ]);

        $this->assertSoftDeleted('contrats', ['id' => $contrat->id]);
    }
}
