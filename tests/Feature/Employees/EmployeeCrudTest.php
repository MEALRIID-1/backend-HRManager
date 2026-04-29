<?php

namespace Tests\Feature\Employees;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests Feature pour le CRUD des employés.
 */
class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $rh;
    private User $employe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->rh = User::factory()->create();
        $this->rh->assignRole('rh');

        $this->employe = User::factory()->create();
        $this->employe->assignRole('employe');
    }

    /** @test */
    public function un_admin_peut_lister_tous_les_employes(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'links',
                ],
            ]);
    }

    /** @test */
    public function un_rh_peut_creer_un_employe(): void
    {
        $employeData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'telephone' => '0123456789',
            'date_embauche' => now()->format('Y-m-d'),
            'role' => 'employe',
        ];

        $response = $this->actingAs($this->rh)
            ->postJson('/api/employees', $employeData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    /** @test */
    public function un_employe_ne_peut_pas_creer_un_employe(): void
    {
        $response = $this->actingAs($this->employe)
            ->postJson('/api/employees', [
                'name' => 'Test',
                'email' => 'test@example.com',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function l_email_doit_etre_unique(): void
    {
        User::factory()->create(['email' => 'existant@example.com']);

        $response = $this->actingAs($this->rh)
            ->postJson('/api/employees', [
                'name' => 'Test',
                'email' => 'existant@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function un_admin_peut_voir_un_employe(): void
    {
        $employe = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/employees/{$employe->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $employe->id,
                ],
            ]);
    }

    /** @test */
    public function un_rh_peut_modifier_un_employe(): void
    {
        $employe = User::factory()->create(['name' => 'Ancien Nom']);

        $response = $this->actingAs($this->rh)
            ->putJson("/api/employees/{$employe->id}", [
                'name' => 'Nouveau Nom',
                'email' => $employe->email,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $employe->id,
            'name' => 'Nouveau Nom',
        ]);
    }

    /** @test */
    public function l_email_reste_unique_meme_lors_d_une_mise_a_jour(): void
    {
        $employe1 = User::factory()->create(['email' => 'user1@example.com']);
        $employe2 = User::factory()->create(['email' => 'user2@example.com']);

        $response = $this->actingAs($this->rh)
            ->putJson("/api/employees/{$employe1->id}", [
                'name' => 'Test',
                'email' => 'user2@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function un_admin_peut_supprimer_un_employe(): void
    {
        $employe = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/employees/{$employe->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('users', ['id' => $employe->id]);
    }

    /** @test */
    public function un_employe_peut_modifier_son_profil(): void
    {
        $response = $this->actingAs($this->employe)
            ->putJson("/api/employees/{$this->employe->id}/profile", [
                'telephone' => '0987654321',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->employe->id,
            'telephone' => '0987654321',
        ]);
    }

    /** @test */
    public function un_employe_ne_peut_pas_modifier_un_autre_employe(): void
    {
        $autreEmploye = User::factory()->create();

        $response = $this->actingAs($this->employe)
            ->putJson("/api/employees/{$autreEmploye->id}", [
                'name' => 'Hacked',
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function la_date_embauche_ne_peut_pas_etre_dans_le_futur(): void
    {
        $response = $this->actingAs($this->rh)
            ->postJson('/api/employees', [
                'name' => 'Test',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'date_embauche' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_embauche']);
    }
}
