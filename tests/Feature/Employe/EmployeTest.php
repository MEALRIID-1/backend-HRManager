<?php

declare(strict_types=1);

namespace Tests\Feature\Employe;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $managerUser;
    private User $rhUser;
    private User $employeUser;
    private string $adminToken;
    private string $managerToken;
    private string $employeToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles
        $adminRole = Role::factory()->create(['slug' => 'admin', 'nom' => 'Administrateur', 'niveau_validation' => 3]);
        $rhRole = Role::factory()->create(['slug' => 'rh', 'nom' => 'Ressources Humaines', 'niveau_validation' => 2]);
        $managerRole = Role::factory()->create(['slug' => 'manager', 'nom' => 'Manager', 'niveau_validation' => 1]);
        $employeRole = Role::factory()->create(['slug' => 'employe', 'nom' => 'Employé', 'niveau_validation' => 0]);

        // Créer les utilisateurs de test
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'departement' => 'IT',
        ]);
        $this->adminUser->roles()->attach($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

        $this->rhUser = User::factory()->create([
            'email' => 'rh@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'departement' => 'RH',
        ]);
        $this->rhUser->roles()->attach($rhRole);

        $this->managerUser = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'departement' => 'Commercial',
        ]);
        $this->managerUser->roles()->attach($managerRole);
        $this->managerToken = $this->managerUser->createToken('test-token')->plainTextToken;

        $this->employeUser = User::factory()->create([
            'email' => 'employe@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'departement' => 'Commercial',
        ]);
        $this->employeUser->roles()->attach($employeRole);
        $this->employeToken = $this->employeUser->createToken('test-token')->plainTextToken;
    }

    /**
     * Test: Admin peut créer un employé.
     */
    public function test_admin_peut_creer_employe(): void
    {
        $employeData = [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@test.com',
            'poste' => 'Développeur',
            'departement' => 'IT',
            'date_embauche' => '2024-01-15',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/employes', $employeData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Employé créé avec succès',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'matricule',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jean.dupont@test.com',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
        ]);
    }

    /**
     * Test: Manager ne peut pas créer un employé (403 Forbidden).
     */
    public function test_manager_ne_peut_pas_creer_employe(): void
    {
        $employeData = [
            'nom' => 'Martin',
            'prenom' => 'Marie',
            'email' => 'marie.martin@test.com',
            'poste' => 'Commercial',
            'departement' => 'Commercial',
            'date_embauche' => '2024-01-15',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->managerToken)
            ->postJson('/api/v1/employes', $employeData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Accès interdit',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'marie.martin@test.com',
        ]);
    }

    /**
     * Test: Employé créé a un mot de passe temporaire.
     */
    public function test_employe_cree_a_password_temporaire(): void
    {
        $employeData = [
            'nom' => 'Petit',
            'prenom' => 'Luc',
            'email' => 'luc.petit@test.com',
            'poste' => 'Comptable',
            'departement' => 'Finance',
            'date_embauche' => '2024-01-15',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/employes', $employeData);

        $response->assertStatus(201);

        $employe = User::where('email', 'luc.petit@test.com')->first();
        
        // Vérifier que le mot de passe est temporaire (généré automatiquement)
        $this->assertNotNull($employe->password);
        $this->assertTrue(Hash::check('Temp' . $employe->matricule . '!', $employe->password));

        // Vérifier que is_temporary_password est true (si ce champ existe)
        // Ou vérifier via une autre logique métier
    }

    /**
     * Test: Soft delete d'un employé.
     */
    public function test_soft_delete_employe(): void
    {
        $employe = User::factory()->create([
            'email' => 'delete.me@test.com',
            'is_active' => true,
        ]);
        $employe->roles()->attach(Role::where('slug', 'employe')->first());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/employes/' . $employe->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employé supprimé avec succès',
            ]);

        // Vérifier le soft delete (l'employé existe toujours en DB mais avec deleted_at)
        $this->assertDatabaseHas('users', [
            'id' => $employe->id,
            'deleted_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Vérifier que l'employé n'est plus visible dans la liste
        $this->assertFalse(User::actif()->where('id', $employe->id)->exists());
    }

    /**
     * Test: Restauration d'un employé supprimé.
     */
    public function test_restauration_employe_supprime(): void
    {
        $employe = User::factory()->create([
            'email' => 'restore.me@test.com',
            'is_active' => true,
        ]);
        $employe->roles()->attach(Role::where('slug', 'employe')->first());
        
        // Soft delete d'abord
        $employe->delete();

        $this->assertTrue(User::withTrashed()->where('id', $employe->id)->exists());

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/employes/' . $employe->id . '/restore');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employé restauré avec succès',
            ]);

        // Vérifier que l'employé est de nouveau actif
        $this->assertTrue(User::actif()->where('id', $employe->id)->exists());
        $this->assertNull(User::find($employe->id)->deleted_at);
    }

    /**
     * Test: Employé peut voir son propre profil.
     */
    public function test_employe_peut_voir_son_profil(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->getJson('/api/v1/employes/' . $this->employeUser->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->employeUser->id,
                    'email' => $this->employeUser->email,
                ],
            ]);
    }

    /**
     * Test: Employé ne peut pas voir les profils des autres employés.
     */
    public function test_employe_ne_peut_pas_voir_autres_employes(): void
    {
        $autreEmploye = User::factory()->create([
            'email' => 'autre@test.com',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->employeToken)
            ->getJson('/api/v1/employes/' . $autreEmploye->id);

        $response->assertStatus(403);
    }

    /**
     * Test: Validation des données lors de la création.
     */
    public function test_validation_creation_employe_email_requis(): void
    {
        $employeData = [
            'nom' => 'Test',
            'prenom' => 'User',
            // Email manquant
            'poste' => 'Test',
            'departement' => 'IT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/employes', $employeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test: Email unique lors de la création.
     */
    public function test_email_unique_creation_employe(): void
    {
        $employeData = [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => $this->employeUser->email, // Email déjà existant
            'poste' => 'Test',
            'departement' => 'IT',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/employes', $employeData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
