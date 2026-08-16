<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class RepairRequestInterventionApiTest extends TestCase
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

    public function test_accepted_artisan_can_start_intervention_once(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.', 'phone' => '+229 90 00 00 00']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->acceptedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequest::STATUS_IN_PROGRESS)
            ->assertJsonPath('data.client.name', 'Roger D.')
            ->assertJsonPath('data.client.phone', '+229 90 00 00 00')
            ->assertJsonPath('data.started_at', fn ($value) => filled($value));

        $startedRequest = $repairRequest->fresh();
        $this->assertSame(RepairRequest::STATUS_IN_PROGRESS, $startedRequest->status);
        $this->assertNotNull($startedRequest->started_at);
        $this->assertFalse($artisan->artisanProfile()->first()->is_available);

        $startedAt = $startedRequest->started_at->copy();
        $this->travel(10)->minutes();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/start")
            ->assertOk()
            ->assertJsonPath('message', 'L’intervention est déjà en cours.');

        $this->assertTrue($startedAt->equalTo($repairRequest->fresh()->started_at));
    }

    public function test_start_endpoint_rejects_wrong_users_and_invalid_statuses(): void
    {
        $client = $this->user(['role' => 'client']);
        $admin = $this->user(['role' => 'admin']);
        $artisan = $this->createArtisan();
        $otherArtisan = $this->createArtisan();
        $acceptedRequest = $this->acceptedRequest($client, $artisan);
        $pendingRequest = $this->requestFor($client, ['accepted_artisan_id' => $artisan->id]);
        $completedRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_COMPLETED,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        $this->actingAs($otherArtisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$acceptedRequest->id}/start")
            ->assertForbidden();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$acceptedRequest->id}/start")
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$acceptedRequest->id}/start")
            ->assertForbidden();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$pendingRequest->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$completedRequest->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_accepted_artisan_can_complete_intervention_once_and_becomes_available(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'started_at' => now()->subHour(),
        ]);

        $this->assertFalse($artisan->artisanProfile()->first()->is_available);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequest::STATUS_COMPLETED)
            ->assertJsonPath('data.completed_at', fn ($value) => filled($value));

        $completedRequest = $repairRequest->fresh();
        $this->assertSame(RepairRequest::STATUS_COMPLETED, $completedRequest->status);
        $this->assertNotNull($completedRequest->completed_at);
        $this->assertTrue($artisan->artisanProfile()->first()->is_available);

        $completedAt = $completedRequest->completed_at->copy();
        $this->travel(10)->minutes();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'L’intervention est déjà terminée.');

        $this->assertTrue($completedAt->equalTo($repairRequest->fresh()->completed_at));
    }

    public function test_complete_endpoint_rejects_wrong_users_and_invalid_statuses(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $otherArtisan = $this->createArtisan();
        $acceptedRequest = $this->acceptedRequest($client, $artisan);
        $inProgressRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(30),
        ]);
        $completedRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_COMPLETED,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$acceptedRequest->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($otherArtisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$inProgressRequest->id}/complete")
            ->assertForbidden();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$inProgressRequest->id}/complete")
            ->assertForbidden();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$completedRequest->id}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'L’intervention est déjà terminée.');
    }

    public function test_manual_availability_is_blocked_during_active_intervention(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $this->acceptedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_available');

        $this->assertFalse($artisan->artisanProfile()->first()->is_available);
    }

    public function test_full_cycle_restores_availability_and_artisan_appears_for_new_request(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->requestFor($client);
        $offer = $this->pendingOffer($repairRequest, $artisan);

        $this->assertTrue($artisan->artisanProfile()->first()->is_available);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$offer->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.request.status', RepairRequest::STATUS_ACCEPTED);

        $this->assertFalse($artisan->artisanProfile()->first()->is_available);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequest::STATUS_IN_PROGRESS);

        $this->assertFalse($artisan->artisanProfile()->first()->is_available);

        $this->travel(5)->minutes();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequest::STATUS_COMPLETED);

        $finished = $repairRequest->fresh();
        $this->assertTrue($artisan->artisanProfile()->first()->is_available);
        $this->assertTrue($finished->accepted_at->lessThan($finished->started_at));
        $this->assertTrue($finished->started_at->lessThan($finished->completed_at));

        $newRequest = $this->requestFor($client, ['title' => 'Nouvelle fuite']);
        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$newRequest->id}/available-artisans")
            ->assertOk();

        $this->assertContains($artisan->id, collect($response->json('data'))->pluck('id')->all());
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
            'address_details' => 'Rue derrière la pharmacie, portail bleu.',
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
