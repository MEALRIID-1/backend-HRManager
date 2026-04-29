<?php

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Tests Feature pour le système de notifications.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->user->assignRole('employe');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    /** @test */
    public function un_utilisateur_peut_voir_ses_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_voir_les_notifications_d_un_autre(): void
    {
        $autreUser = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $autreUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.data');
    }

    /** @test */
    public function un_utilisateur_peut_marquer_une_notification_comme_lue(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read' => true,
            'read_at' => now(),
        ]);
    }

    /** @test */
    public function un_utilisateur_ne_peut_pas_marquer_une_notification_d_un_autre(): void
    {
        $autreUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $autreUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(403);
    }

    /** @test */
    public function un_utilisateur_peut_marquer_toutes_les_notifications_comme_lues(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/notifications/read-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseCount('notifications', [
            'user_id' => $this->user->id,
            'read' => false,
        ], 0);
    }

    /** @test */
    public function le_nombre_de_notifications_non_lues_est_retourne(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'read' => false,
        ]);
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'read' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3,
                ],
            ]);
    }

    /** @test */
    public function un_utilisateur_peut_supprimer_une_notification(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    /** @test */
    public function les_notifications_recentes_sont_listees(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications/recent');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function le_type_et_priorite_sont_retournes(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'conge_approved',
            'priority' => 'high',
            'title' => 'Congé approuvé',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'type' => 'conge_approved',
                'priority' => 'high',
                'title' => 'Congé approuvé',
            ]);
    }
}
