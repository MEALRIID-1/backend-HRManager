<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rh;
    private User $employe;
    private string $adminToken;
    private string $rhToken;
    private string $employeToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $rhRole = Role::factory()->create(['slug' => 'rh']);
        $employeRole = Role::factory()->create(['slug' => 'employe']);

        // Create users
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole);

        $this->rh = User::factory()->create([
            'email' => 'rh@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->rh->roles()->attach($rhRole);

        $this->employe = User::factory()->create([
            'email' => 'employe@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->employe->roles()->attach($employeRole);

        // Get tokens
        $this->adminToken = $this->getToken($this->admin);
        $this->rhToken = $this->getToken($this->rh);
        $this->employeToken = $this->getToken($this->employe);
    }

    private function getToken(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        return $response->json('data.token');
    }

    /**
     * Test list employes.
     */
    public function test_list_employes(): void
    {
        User::factory()->count(5)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->getJson('/api/v1/employes');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
    }

    /**
     * Test create employe.
     */
    public function test_create_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->postJson('/api/v1/employes', [
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@company.com',
            'matricule' => 'EMP-123',
            'poste' => 'Développeur',
            'departement' => 'IT',
            'date_embauche' => now()->format('Y-m-d'),
            'telephone' => '0123456789',
        ]);

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
                ],
            ]);
    }

    /**
     * Test create employe validation error.
     */
    public function test_create_employe_validation_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->postJson('/api/v1/employes', [
            'nom' => '',
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
            ]);
    }

    /**
     * Test show employe.
     */
    public function test_show_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->getJson("/api/v1/employes/{$this->employe->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                ],
            ]);
    }

    /**
     * Test update employe.
     */
    public function test_update_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->putJson("/api/v1/employes/{$this->employe->id}", [
            'nom' => 'Dupont Modifié',
            'poste' => 'Senior Développeur',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employé mis à jour avec succès',
            ]);

        $this->employe->refresh();
        $this->assertEquals('Dupont Modifié', $this->employe->nom);
        $this->assertEquals('Senior Développeur', $this->employe->poste);
    }

    /**
     * Test delete employe (soft delete).
     */
    public function test_delete_employe(): void
    {
        $employeToDelete = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->deleteJson("/api/v1/employes/{$employeToDelete->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employé supprimé avec succès',
            ]);

        $this->assertSoftDeleted('users', ['id' => $employeToDelete->id]);
    }

    /**
     * Test restore employe.
     */
    public function test_restore_employe(): void
    {
        $employeToRestore = User::factory()->create();
        $employeToRestore->delete();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/v1/employes/{$employeToRestore->id}/restore");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Employé restauré avec succès',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $employeToRestore->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * Test unauthorized access to create employe.
     */
    public function test_unauthorized_create_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->employeToken,
        ])->postJson('/api/v1/employes', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Vous n\'avez pas les permissions nécessaires pour effectuer cette action.',
            ]);
    }

    /**
     * Test not found employe.
     */
    public function test_not_found_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->getJson('/api/v1/employes/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Employé non trouvé',
            ]);
    }

    /**
     * Test filter employes by department.
     */
    public function test_filter_employes_by_department(): void
    {
        User::factory()->create(['departement' => 'IT']);
        User::factory()->create(['departement' => 'RH']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->getJson('/api/v1/employes?departement=IT');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
