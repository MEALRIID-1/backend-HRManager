<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests pour l'upload de fichiers.
 */
class FileUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        Storage::fake('documents');
        Storage::fake('payslips');
    }

    /** @test */
    public function un_utilisateur_peut_uploader_sa_propre_photo(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Photo de profil mise à jour.',
            ]);

        Storage::disk('photos')->assertExists('profiles/' . $user->id);
    }

    /** @test */
    public function un_admin_peut_uploader_la_photo_d_un_autre_utilisateur(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('photo.png', 800, 600);

        $response = $this->actingAs($admin)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_uploader_la_photo_d_un_autre(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user1)
            ->postJson("/api/files/photo/{$user2->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function une_photo_doit_etre_de_type_valide(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($user)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function une_photo_ne_doit_pas_depasser_2mo(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('photo.jpg')->size(3000); // 3MB

        $response = $this->actingAs($user)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function un_rh_peut_uploader_un_document_pdf(): void
    {
        $rh = User::factory()->create();
        $rh->assignRole('rh');

        $file = UploadedFile::fake()->create('contrat.pdf', 500, 'application/pdf');

        $response = $this->actingAs($rh)
            ->postJson('/api/files/upload', [
                'file' => $file,
                'type' => 'document',
                'path' => 'contrats/2024',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        Storage::disk('documents')->assertExists('contrats/2024');
    }

    /** @test */
    public function un_document_doit_etre_un_pdf(): void
    {
        $rh = User::factory()->create();
        $rh->assignRole('rh');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($rh)
            ->postJson('/api/files/upload', [
                'file' => $file,
                'type' => 'document',
                'path' => 'test',
            ]);

        $response->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function l_upload_genere_un_nom_hash(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('ma_super_photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        // Vérifier que le nom est un hash (64 caractères hex + .webp)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.webp$/', $data['photo_path']);
    }

    /** @test */
    public function l_ancienne_photo_est_supprimee_lors_du_remplacement(): void
    {
        $user = User::factory()->create(['photo_path' => 'profiles/1/old_photo.webp']);

        Storage::disk('photos')->put('profiles/1/old_photo.webp', 'fake-content');

        $file = UploadedFile::fake()->image('new_photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson("/api/files/photo/{$user->id}", [
                'photo' => $file,
            ]);

        $response->assertStatus(200);

        Storage::disk('photos')->assertMissing('profiles/1/old_photo.webp');
    }

    /** @test */
    public function l_authentification_est_requise_pour_uploader(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->postJson('/api/files/upload', [
            'file' => $file,
            'type' => 'photo',
            'path' => 'test',
        ]);

        $response->assertStatus(401);
    }
}
