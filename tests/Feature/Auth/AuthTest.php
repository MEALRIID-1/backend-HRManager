<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $employeUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les rôles
        $adminRole = Role::factory()->create(['slug' => 'admin', 'nom' => 'Administrateur']);
        $employeRole = Role::factory()->create(['slug' => 'employe', 'nom' => 'Employé']);

        // Créer les utilisateurs de test
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole);

        $this->employeUser = User::factory()->create([
            'email' => 'employe@test.com',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
        ]);
        $this->employeUser->roles()->attach($employeRole);
    }

    /**
     * Test: Login avec credentials valides retourne un token.
     */
    public function test_login_avec_credentials_valides(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'nom',
                        'prenom',
                        'email',
                        'roles',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Connexion réussie',
            ]);

        $this->assertArrayHasKey('token', $response->json('data'));
    }

    /**
     * Test: Login échoue avec mauvais password.
     */
    public function test_login_echoue_avec_mauvais_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ]);
    }

    /**
     * Test: Logout révoque le token Sanctum.
     */
    public function test_logout_revoque_token(): void
    {
        // Créer un token pour l'utilisateur
        $token = $this->adminUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);

        // Vérifier que le token a été révoqué
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Test: /me retourne l'utilisateur connecté.
     */
    public function test_me_retourne_utilisateur_connecte(): void
    {
        $token = $this->adminUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'nom',
                    'prenom',
                    'email',
                    'roles' => [],
                    'permissions' => [],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->adminUser->id,
                    'email' => $this->adminUser->email,
                ],
            ]);
    }

    /**
     * Test: Change password échoue avec ancien password incorrect.
     */
    public function test_change_password_avec_ancien_password_incorrect(): void
    {
        $token = $this->adminUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongPassword123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test: Change password réussit avec ancien password correct.
     */
    public function test_change_password_avec_ancien_password_correct(): void
    {
        $token = $this->adminUser->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Password123!',
                'new_password' => 'NewPassword456!',
                'new_password_confirmation' => 'NewPassword456!',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès',
            ]);

        // Vérifier que le mot de passe a été mis à jour
        $this->assertTrue(Hash::check('NewPassword456!', $this->adminUser->fresh()->password));
    }

    /**
     * Test: Accès à une route protégée sans token retourne 401.
     */
    public function test_acces_route_protegee_sans_token(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    /**
     * Test: Validation du login avec email manquant.
     */
    public function test_login_validation_email_requis(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test: Validation du login avec password manquant.
     */
    public function test_login_validation_password_requis(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@test.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
