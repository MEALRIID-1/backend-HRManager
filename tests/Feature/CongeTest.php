<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Conge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CongeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rh;
    private User $manager;
    private User $employe;
    private string $adminToken;
    private string $rhToken;
    private string $managerToken;
    private string $employeToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $adminRole = Role::factory()->create(['slug' => 'admin', 'niveau_validation' => 3]);
        $rhRole = Role::factory()->create(['slug' => 'rh', 'niveau_validation' => 2]);
        $managerRole = Role::factory()->create(['slug' => 'manager', 'niveau_validation' => 1]);
        $employeRole = Role::factory()->create(['slug' => 'employe', 'niveau_validation' => 0]);

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

        $this->manager = User::factory()->create([
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->manager->roles()->attach($managerRole);

        $this->employe = User::factory()->create([
            'email' => 'employe@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->employe->roles()->attach($employeRole);

        // Get tokens
        $this->adminToken = $this->getToken($this->admin);
        $this->rhToken = $this->getToken($this->rh);
        $this->managerToken = $this->getToken($this->manager);
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
     * Test create congé by employe.
     */
    public function test_create_conge_by_employe(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->employeToken,
        ])->postJson('/api/v1/conges', [
            'type' => 'conge_paye',
            'date_debut' => now()->addDays(5)->format('Y-m-d'),
            'date_fin' => now()->addDays(10)->format('Y-m-d'),
            'commentaire' => 'Test congé',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Demande de congé créée avec succès',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'type',
                    'date_debut',
                    'date_fin',
                    'etat',
                ],
            ]);
    }

    /**
     * Test create congé validation error.
     */
    public function test_create_conge_validation_error(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->employeToken,
        ])->postJson('/api/v1/conges', [
            'type' => '',
            'date_debut' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
            ]);
    }

    /**
     * Test validate N1 by manager.
     */
    public function test_validate_n1_by_manager(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->managerToken,
        ])->postJson("/api/v1/conges/{$conge->id}/valider", [
            'niveau' => 1,
            'commentaire' => 'Validation N1 OK',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé validé au niveau N1',
            ]);
    }

    /**
     * Test validate N2 by RH.
     */
    public function test_validate_n2_by_rh(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'partiellement_valide',
            'niveau_validation' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->rhToken,
        ])->postJson("/api/v1/conges/{$conge->id}/valider", [
            'niveau' => 2,
            'commentaire' => 'Validation N2 OK',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé validé au niveau N2',
            ]);
    }

    /**
     * Test super validation by admin.
     */
    public function test_super_validation_by_admin(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'en_attente',
            'niveau_validation' => 0,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->postJson("/api/v1/conges/{$conge->id}/valider", [
            'niveau' => 3,
            'commentaire' => 'Validation finale admin',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé validé définitivement',
            ]);

        $conge->refresh();
        $this->assertEquals('approuve', $conge->etat);
    }

    /**
     * Test reject congé.
     */
    public function test_reject_conge(): void
    {
        $conge = Conge::factory()->create([
            'employe_id' => $this->employe->id,
            'etat' => 'en_attente',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->managerToken,
        ])->postJson("/api/v1/conges/{$conge->id}/refuser", [
            'motif_refus' => 'Manque de personnel',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Congé refusé',
            ]);

        $conge->refresh();
        $this->assertEquals('refuse', $conge->etat);
    }

    /**
     * Test list conges.
     */
    public function test_list_conges(): void
    {
        Conge::factory()->count(5)->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->employeToken,
        ])->getJson('/api/v1/conges');

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
     * Test unauthorized access to create conge.
     */
    public function test_unauthorized_create_conge(): void
    {
        $response = $this->postJson('/api/v1/conges', [
            'type' => 'conge_paye',
            'date_debut' => now()->addDays(5)->format('Y-m-d'),
            'date_fin' => now()->addDays(10)->format('Y-m-d'),
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test get my conges.
     */
    public function test_get_my_conges(): void
    {
        Conge::factory()->count(3)->create([
            'employe_id' => $this->employe->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->employeToken,
        ])->getJson('/api/v1/conges/mes-conges');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
