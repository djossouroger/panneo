<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class DisputeApiTest extends TestCase
{
    use RefreshDatabase, InteractsWithArtisans;

    private Category $plumbing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plumbing = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => 'plumbing', 'is_active' => true]);
    }

    public function test_client_can_open_dispute_on_accepted_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/disputes", [
                'subject' => 'Absence du professionnel',
                'description' => 'Le dépanneur n’est pas venu à l’heure convenue.',
                'type' => Dispute::TYPE_NO_SHOW,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', Dispute::STATUS_OPEN)
            ->assertJsonPath('data.type_label', 'Absence du professionnel');
    }

    public function test_artisan_can_open_dispute_on_completed_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_COMPLETED, 'accepted_artisan_id' => $artisan->id]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/disputes", [
                'subject' => 'Client injoignable',
                'description' => 'Le client n’a pas répondu aux relances.',
                'type' => Dispute::TYPE_OTHER,
            ])
            ->assertCreated();
    }

    public function test_cannot_open_dispute_on_pending_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/disputes", [
                'subject' => 'Test',
                'description' => 'Description.',
                'type' => Dispute::TYPE_OTHER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_non_participant_cannot_open_dispute(): void
    {
        $client = $this->user(['role' => 'client']);
        $stranger = $this->user(['role' => 'client', 'email' => 'stranger@example.com']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/disputes", [
                'subject' => 'Test',
                'description' => 'Description.',
                'type' => Dispute::TYPE_OTHER,
            ])
            ->assertForbidden();
    }

    public function test_dispute_index_lists_only_participants_disputes(): void
    {
        $client = $this->user(['role' => 'client']);
        $stranger = $this->user(['role' => 'client', 'email' => 'stranger@example.com']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        Dispute::create([
            'repair_request_id' => $repairRequest->id,
            'reporter_id' => $client->id,
            'subject' => 'Absence',
            'description' => 'Pas venu.',
            'type' => Dispute::TYPE_NO_SHOW,
            'status' => Dispute::STATUS_OPEN,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/disputes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/disputes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/v1/disputes')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_non_participant_cannot_view_dispute(): void
    {
        $client = $this->user(['role' => 'client']);
        $stranger = $this->user(['role' => 'client', 'email' => 'stranger@example.com']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        $dispute = Dispute::create([
            'repair_request_id' => $repairRequest->id,
            'reporter_id' => $client->id,
            'subject' => 'Absence',
            'description' => 'Pas venu.',
            'type' => Dispute::TYPE_NO_SHOW,
            'status' => Dispute::STATUS_OPEN,
        ]);

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/disputes/{$dispute->id}")
            ->assertForbidden();
    }

    public function test_admin_update_sends_notification_to_participants(): void
    {
        $admin = $this->user(['role' => 'admin', 'name' => 'Admin']);
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        $dispute = Dispute::create([
            'repair_request_id' => $repairRequest->id,
            'reporter_id' => $client->id,
            'subject' => 'Absence',
            'description' => 'Pas venu.',
            'type' => Dispute::TYPE_NO_SHOW,
            'status' => Dispute::STATUS_OPEN,
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.disputes.update', $dispute), [
                'status' => Dispute::STATUS_RESOLVED,
                'resolution_notes' => 'Remboursement effectué.',
            ])
            ->assertRedirect();

        $dispute->refresh();
        $this->assertSame(Dispute::STATUS_RESOLVED, $dispute->status);
        $this->assertNotNull($dispute->resolved_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_DISPUTE_STATUS_UPDATED,
        ]);
    }

    public function test_dispute_status_update_notifies_other_participant(): void
    {
        $admin = $this->user(['role' => 'admin', 'name' => 'Admin']);
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED, 'accepted_artisan_id' => $artisan->id]);

        $dispute = Dispute::create([
            'repair_request_id' => $repairRequest->id,
            'reporter_id' => $artisan->id,
            'subject' => 'Client injoignable',
            'description' => 'Relances sans réponse.',
            'type' => Dispute::TYPE_OTHER,
            'status' => Dispute::STATUS_OPEN,
        ]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.disputes.update', $dispute), [
                'status' => Dispute::STATUS_IN_REVIEW,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_DISPUTE_STATUS_UPDATED,
        ]);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function requestFor(User $client, array $overrides = []): RepairRequest
    {
        $repairRequest = RepairRequest::create(array_merge([
            'client_id' => $client->id,
            'category_id' => $this->plumbing->id,
            'title' => 'Fuite sous l’évier',
            'description' => 'Un tuyau s’est détaché.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'status' => RepairRequest::STATUS_PENDING,
        ], $overrides));

        $repairRequest->assignReference();

        return $repairRequest->fresh();
    }
}
