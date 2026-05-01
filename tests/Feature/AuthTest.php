<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    /**
     * Test login success.
     */
    public function test_login_success(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Connexion réussie',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);
    }

    /**
     * Test login failure with wrong password.
     */
    public function test_login_failure_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ]);
    }

    /**
     * Test login failure with non-existent email.
     */
    public function test_login_failure_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect',
            ]);
    }

    /**
     * Test login validation errors.
     */
    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Les données fournies sont invalides.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    /**
     * Test logout success.
     */
    public function test_logout_success(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
    }

    /**
     * Test logout without authentication.
     */
    public function test_logout_without_auth(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Authentification requise.',
            ]);
    }

    /**
     * Test get current user (me).
     */
    public function test_get_current_user(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

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
     * Test change password success.
     */
    public function test_change_password_success(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Mot de passe changé avec succès',
            ]);

        // Verify new password works
        $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'newpassword123',
        ])->assertStatus(200);
    }

    /**
     * Test change password with wrong current password.
     */
    public function test_change_password_wrong_current(): void
    {
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrongpassword',
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect',
            ]);
    }
}
