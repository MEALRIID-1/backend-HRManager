<?php

namespace Tests\Feature;

use App\Models\Contrat;
use App\Models\FichePaie;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests pour l'accès aux fichiers via URLs signées.
 */
class SignedUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('photos');
        Storage::fake('documents');
        Storage::fake('payslips');
    }

    /** @test */
    public function un_utilisateur_peut_telecharger_sa_propre_photo(): void
    {
        $user = User::factory()->create([
            'photo_path' => 'profiles/1/photo.webp',
        ]);

        Storage::disk('photos')->put('profiles/1/photo.webp', 'fake-content');

        $response = $this->actingAs($user)
            ->getJson('/api/files/download/photo/1');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['download_url', 'expires_in', 'filename'],
            ]);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_telecharger_la_photo_d_un_autre(): void
    {
        $user1 = User::factory()->create(['photo_path' => 'profiles/1/photo.webp']);
        $user2 = User::factory()->create();

        Storage::disk('photos')->put('profiles/1/photo.webp', 'fake-content');

        $response = $this->actingAs($user2)
            ->getJson('/api/files/download/photo/1');

        $response->assertStatus(403);
    }

    /** @test */
    public function un_rh_peut_telecharger_toutes_les_photos(): void
    {
        $rh = User::factory()->create();
        $rh->assignRole('rh');
        $user = User::factory()->create(['photo_path' => 'profiles/2/photo.webp']);

        Storage::disk('photos')->put('profiles/2/photo.webp', 'fake-content');

        $response = $this->actingAs($rh)
            ->getJson("/api/files/download/photo/{$user->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function un_employe_peut_telecharger_son_propre_contrat(): void
    {
        $user = User::factory()->create();
        $contrat = Contrat::factory()->create([
            'employe_id' => $user->id,
            'document_path' => 'contrats/2024/contrat_1.pdf',
        ]);

        Storage::disk('documents')->put('contrats/2024/contrat_1.pdf', 'fake-content');

        $response = $this->actingAs($user)
            ->getJson("/api/files/download/contrat/{$contrat->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function un_employe_ne_peut_pas_telecharger_le_contrat_d_un_autre(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $contrat = Contrat::factory()->create([
            'employe_id' => $user1->id,
            'document_path' => 'contrats/2024/contrat_1.pdf',
        ]);

        Storage::disk('documents')->put('contrats/2024/contrat_1.pdf', 'fake-content');

        $response = $this->actingAs($user2)
            ->getJson("/api/files/download/contrat/{$contrat->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_employe_peut_telecharger_sa_propre_fiche_de_paie(): void
    {
        $user = User::factory()->create();
        $payslip = FichePaie::factory()->create([
            'employe_id' => $user->id,
            'document_path' => 'payslips/2024/01.pdf',
            'mois' => 1,
            'annee' => 2024,
        ]);

        Storage::disk('payslips')->put('payslips/2024/01.pdf', 'fake-content');

        $response = $this->actingAs($user)
            ->getJson("/api/files/download/payslip/{$payslip->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function un_manager_ne_peut_pas_telecharger_les_fiches_de_paie(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $employe = User::factory()->create(['manager_id' => $manager->id]);
        $payslip = FichePaie::factory()->create([
            'employe_id' => $employe->id,
            'document_path' => 'payslips/2024/01.pdf',
        ]);

        Storage::disk('payslips')->put('payslips/2024/01.pdf', 'fake-content');

        $response = $this->actingAs($manager)
            ->getJson("/api/files/download/payslip/{$payslip->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_rh_peut_telecharger_toutes_les_fiches_de_paie(): void
    {
        $rh = User::factory()->create();
        $rh->assignRole('rh');

        $employe = User::factory()->create();
        $payslip = FichePaie::factory()->create([
            'employe_id' => $employe->id,
            'document_path' => 'payslips/2024/01.pdf',
        ]);

        Storage::disk('payslips')->put('payslips/2024/01.pdf', 'fake-content');

        $response = $this->actingAs($rh)
            ->getJson("/api/files/download/payslip/{$payslip->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function l_url_signee_expire_apres_60_minutes_par_defaut(): void
    {
        $user = User::factory()->create([
            'photo_path' => 'profiles/1/photo.webp',
        ]);

        Storage::disk('photos')->put('profiles/1/photo.webp', 'fake-content');

        $response = $this->actingAs($user)
            ->getJson('/api/files/download/photo/1');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(60, $data['expires_in']);
    }

    /** @test */
    public function une_erreur_404_est_retournee_si_fichier_inexistant(): void
    {
        $user = User::factory()->create([
            'photo_path' => 'profiles/1/inexistant.webp',
        ]);

        // Ne pas créer le fichier dans le storage fake

        $response = $this->actingAs($user)
            ->getJson('/api/files/download/photo/1');

        $response->assertStatus(404);
    }

    /** @test */
    public function l_authentification_est_requise_pour_telecharger(): void
    {
        $response = $this->getJson('/api/files/download/photo/1');

        $response->assertStatus(401);
    }

    /** @test */
    public function le_middleware_secure_file_access_bloque_les_acces_non_autorises(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/files/download/payslip/99999'); // ID inexistant

        $response->assertStatus(403); // Ou 404 selon l'implémentation
    }
}
