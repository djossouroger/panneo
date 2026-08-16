<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase, InteractsWithArtisans;

    private Category $plumbing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plumbing = Category::create([
            'name' => 'Plomberie',
            'slug' => 'plomberie',
            'icon' => 'plumbing',
            'is_active' => true,
        ]);
    }

    public function test_offer_created_notifies_artisan(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/offers", ['artisan_id' => $artisan->id])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_RECEIVED,
            'title' => 'Nouvelle demande de dépannage',
        ]);

        $notification = $artisan->notifications()->first();
        $this->assertSame((string) $repairRequest->id, (string) ($notification->data['repair_request_id'] ?? ''));
        $this->assertSame($repairRequest->reference, $notification->data['reference'] ?? null);
    }

    public function test_offer_accepted_notifies_client(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->requestFor($client);
        $offer = $this->pendingOffer($repairRequest, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$offer->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Votre demande a été acceptée',
        ]);

        $notification = $client->notifications()->first();
        $this->assertSame($repairRequest->reference, $notification->data['reference'] ?? null);
    }

    public function test_offer_rejected_notifies_client(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->requestFor($client);
        $offer = $this->pendingOffer($repairRequest, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$offer->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_REJECTED,
            'title' => 'Le dépanneur n’est pas disponible',
        ]);
    }

    public function test_intervention_started_notifies_client(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->acceptedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/start")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_STARTED,
            'title' => 'Intervention commencée',
        ]);
    }

    public function test_intervention_completed_notifies_client_once(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_COMPLETED,
            'title' => 'Dépannage terminé',
        ]);

        $this->assertSame(1, $client->notifications()->where('type', Notification::TYPE_REPAIR_REQUEST_COMPLETED)->count());

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/complete")
            ->assertOk();

        $this->assertSame(1, $client->notifications()->where('type', Notification::TYPE_REPAIR_REQUEST_COMPLETED)->count());
    }

    public function test_review_created_notifies_artisan(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => Notification::TYPE_REVIEW_RECEIVED,
            'title' => 'Nouvel avis reçu',
        ]);
    }

    public function test_client_sees_only_own_notifications(): void
    {
        $clientA = $this->user(['role' => 'client', 'name' => 'Roger']);
        $clientB = $this->user(['role' => 'client', 'name' => 'Paul']);

        Notification::create([
            'user_id' => $clientA->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Votre demande a été acceptée',
            'message' => 'Jean D. a accepté votre demande.',
        ]);
        Notification::create([
            'user_id' => $clientB->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Votre demande a été acceptée',
            'message' => 'Paul K. a accepté votre demande.',
        ]);

        $this->actingAs($clientA, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Jean D. a accepté votre demande.');
    }

    public function test_client_cannot_mark_other_notification_as_read(): void
    {
        $clientA = $this->user(['role' => 'client', 'name' => 'Roger']);
        $clientB = $this->user(['role' => 'client', 'name' => 'Paul']);

        $notification = Notification::create([
            'user_id' => $clientA->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Votre demande a été acceptée',
            'message' => 'Jean D. a accepté votre demande.',
        ]);

        $this->actingAs($clientB, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unread_count_reflects_read_operations(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger']);

        foreach (range(1, 3) as $i) {
            Notification::create([
                'user_id' => $client->id,
                'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
                'title' => "Notification $i",
                'message' => "Message $i",
            ]);
        }

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 3);

        $first = $client->notifications()->first();

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read_at', fn ($value) => filled($value));

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->actingAs($client, 'sanctum')
            ->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.count', 0);
    }

    public function test_notifications_are_ordered_desc(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger']);

        Notification::create([
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Ancienne',
            'message' => 'Ancienne notification',
            'created_at' => now()->subDay(),
        ]);
        Notification::create([
            'user_id' => $client->id,
            'type' => Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
            'title' => 'Récente',
            'message' => 'Notification récente',
            'created_at' => now(),
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Récente')
            ->assertJsonPath('data.1.title', 'Ancienne');
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/v1/notifications/read-all')->assertUnauthorized();
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function createArtisan(array $userAttributes = [], array $profileAttributes = []): User
    {
        $artisan = $this->user(array_merge(['role' => 'artisan'], $userAttributes));

        $categoryId = $profileAttributes['category_id'] ?? $this->plumbing->id;
        $city = $profileAttributes['city'] ?? 'Cotonou';
        $district = $profileAttributes['district'] ?? 'Akpakpa';

        ArtisanProfile::create(array_merge([
            'user_id' => $artisan->id,
            'category_id' => $categoryId,
            'city' => $city,
            'district' => $district,
            'description' => 'Dépanneur disponible à Cotonou.',
            'is_available' => true,
            'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
        ], $profileAttributes));

        $artisan->categories()->attach($categoryId, ['is_primary' => true]);
        $artisan->serviceAreas()->create(['city' => $city, 'district' => $district]);

        return $artisan;
    }

    private function requestFor(User $client, array $overrides = []): RepairRequest
    {
        $repairRequest = RepairRequest::create(array_merge([
            'client_id' => $client->id,
            'category_id' => $this->plumbing->id,
            'title' => 'Fuite sous l’évier',
            'description' => 'Un tuyau s’est détaché et l’eau coule sous l’évier.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'status' => RepairRequest::STATUS_PENDING,
        ], $overrides));

        $repairRequest->assignReference();

        return $repairRequest->fresh();
    }

    private function acceptedRequest(User $client, User $artisan, array $overrides = []): RepairRequest
    {
        $request = $this->requestFor($client, array_merge([
            'status' => RepairRequest::STATUS_ACCEPTED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subMinutes(10),
        ], $overrides));

        $artisan->artisanProfile()->update(['is_available' => false]);

        return $request->fresh();
    }

    private function completedRequest(User $client, User $artisan): RepairRequest
    {
        $request = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_COMPLETED,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(5),
        ]);

        return $request->fresh();
    }

    private function pendingOffer(RepairRequest $repairRequest, User $artisan): RepairRequestOffer
    {
        $offer = RepairRequestOffer::create([
            'repair_request_id' => $repairRequest->id,
            'artisan_id' => $artisan->id,
            'status' => RepairRequestOffer::STATUS_PENDING,
        ]);

        $repairRequest->forceFill(['status' => RepairRequest::STATUS_AWAITING_ARTISAN])->save();

        return $offer->fresh();
    }
}
