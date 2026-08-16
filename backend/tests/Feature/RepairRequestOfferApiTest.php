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

class RepairRequestOfferApiTest extends TestCase
{
    use RefreshDatabase, InteractsWithArtisans;

    private Category $plumbing;
    private Category $electricity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plumbing = Category::create([
            'name' => 'Plomberie',
            'slug' => 'plomberie',
            'icon' => 'plumbing',
            'is_active' => true,
        ]);

        $this->electricity = Category::create([
            'name' => 'Ã‰lectricitÃ©',
            'slug' => 'electricite',
            'icon' => 'electricity',
            'is_active' => true,
        ]);
    }

    public function test_available_artisans_are_filtered_and_do_not_expose_private_contact(): void
    {
        $client = $this->user(['role' => 'client']);
        $repairRequest = $this->requestFor($client, ['city' => 'cotonou']);
        $matching = $this->createArtisan([
            'name' => 'Jean D.',
            'email' => 'jean@example.com',
            'phone' => '+229 61 00 00 01',
        ], ['city' => 'COTONOU', 'district' => 'Akpakpa']);
        $wrongCategory = $this->createArtisan(['name' => 'Electricien'], ['category_id' => $this->electricity->id, 'city' => 'Cotonou']);
        $otherCity = $this->createArtisan(['name' => 'Porto Artisan'], ['city' => 'Porto-Novo']);
        $unavailable = $this->createArtisan(['name' => 'OccupÃ©'], ['is_available' => false]);
        $inactive = $this->createArtisan(['name' => 'Inactif', 'is_active' => false]);
        $rejected = $this->createArtisan(['name' => 'DÃ©jÃ  refusÃ©']);

        RepairRequestOffer::create([
            'repair_request_id' => $repairRequest->id,
            'artisan_id' => $rejected->id,
            'status' => RepairRequestOffer::STATUS_REJECTED,
            'responded_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/available-artisans")
            ->assertOk()
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.city', 'COTONOU')
            ->assertJsonMissing(['email' => 'jean@example.com'])
            ->assertJsonMissing(['phone' => '+229 61 00 00 01']);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$matching->id], $ids);
        $this->assertNotContains($wrongCategory->id, $ids);
        $this->assertNotContains($otherCity->id, $ids);
        $this->assertNotContains($unavailable->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }

    public function test_client_owner_can_send_request_to_compatible_artisan(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/offers", ['artisan_id' => $artisan->id])
            ->assertCreated()
            ->assertJsonPath('data.status', RepairRequestOffer::STATUS_PENDING)
            ->assertJsonPath('data.artisan.name', 'Jean D.')
            ->assertJsonPath('data.request.status', RepairRequest::STATUS_AWAITING_ARTISAN);

        $this->assertDatabaseHas('repair_request_offers', [
            'repair_request_id' => $repairRequest->id,
            'artisan_id' => $artisan->id,
            'status' => RepairRequestOffer::STATUS_PENDING,
        ]);
        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'status' => RepairRequest::STATUS_AWAITING_ARTISAN,
        ]);
    }

    public function test_other_client_cannot_send_or_search_for_someone_elses_request(): void
    {
        $owner = $this->user(['role' => 'client']);
        $other = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($owner);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/available-artisans")
            ->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/offers", ['artisan_id' => $artisan->id])
            ->assertForbidden();
    }

    public function test_incompatible_unavailable_or_invalid_request_states_are_rejected(): void
    {
        $client = $this->user(['role' => 'client']);
        $wrongCategory = $this->createArtisan(['name' => 'Mauvais mÃ©tier'], ['category_id' => $this->electricity->id]);
        $unavailable = $this->createArtisan(['name' => 'Indisponible'], ['is_available' => false]);
        $cancelledRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_CANCELLED]);
        $acceptedRequest = $this->requestFor($client, ['status' => RepairRequest::STATUS_ACCEPTED]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$this->requestFor($client)->id}/offers", ['artisan_id' => $wrongCategory->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artisan_id');

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$this->requestFor($client)->id}/offers", ['artisan_id' => $unavailable->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artisan_id');

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$cancelledRequest->id}/offers", ['artisan_id' => $this->createArtisan()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$acceptedRequest->id}/offers", ['artisan_id' => $this->createArtisan()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_second_active_offer_is_forbidden(): void
    {
        $client = $this->user(['role' => 'client']);
        $firstArtisan = $this->createArtisan(['name' => 'Jean']);
        $secondArtisan = $this->createArtisan(['name' => 'Paul']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/offers", ['artisan_id' => $firstArtisan->id])
            ->assertCreated();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/offers", ['artisan_id' => $secondArtisan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_artisan_sees_only_own_offers_and_pending_offer_hides_private_client_data(): void
    {
        $client = $this->user(['role' => 'client', 'phone' => '+229 90 00 00 00']);
        $artisan = $this->createArtisan(['name' => 'Jean']);
        $otherArtisan = $this->createArtisan(['name' => 'Paul']);
        $ownOffer = $this->pendingOffer($this->requestFor($client), $artisan);
        $otherOffer = $this->pendingOffer($this->requestFor($client), $otherArtisan);

        $response = $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/artisan/offers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownOffer->id)
            ->assertJsonPath('data.0.request.client', null)
            ->assertJsonPath('data.0.request.location.address_details', null);

        $this->assertSame($ownOffer->id, $response->json('data.0.id'));

        $this->actingAs($artisan, 'sanctum')
            ->getJson("/api/v1/artisan/offers/{$otherOffer->id}")
            ->assertForbidden();
    }

    public function test_artisan_accepts_offer_unlocks_contacts_and_becomes_unavailable(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.', 'phone' => '+229 90 00 00 00']);
        $artisan = $this->createArtisan(['name' => 'Jean D.', 'phone' => '+229 61 00 00 01']);
        $repairRequest = $this->requestFor($client);
        $offer = $this->pendingOffer($repairRequest, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$offer->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequestOffer::STATUS_ACCEPTED)
            ->assertJsonPath('data.request.status', RepairRequest::STATUS_ACCEPTED)
            ->assertJsonPath('data.request.client.phone', '+229 90 00 00 00')
            ->assertJsonPath('data.request.location.address_details', 'Rue derriÃ¨re la pharmacie, portail bleu.');

        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'status' => RepairRequest::STATUS_ACCEPTED,
            'accepted_artisan_id' => $artisan->id,
        ]);
        $this->assertFalse($artisan->artisanProfile()->first()->is_available);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.artisan.name', 'Jean D.')
            ->assertJsonPath('data.artisan.phone', '+229 61 00 00 01');
    }

    public function test_artisan_rejects_offer_request_returns_pending_and_artisan_is_not_reproposed(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $otherArtisan = $this->createArtisan(['name' => 'Paul K.']);
        $repairRequest = $this->requestFor($client);
        $offer = $this->pendingOffer($repairRequest, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$offer->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequestOffer::STATUS_REJECTED)
            ->assertJsonPath('data.request.status', RepairRequest::STATUS_PENDING);

        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'status' => RepairRequest::STATUS_PENDING,
            'accepted_artisan_id' => null,
        ]);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($artisan->id, $ids);
        $this->assertContains($otherArtisan->id, $ids);
    }

    public function test_double_answer_and_accept_after_client_cancellation_are_impossible(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean']);
        $firstRequest = $this->requestFor($client);
        $firstOffer = $this->pendingOffer($firstRequest, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$firstOffer->id}/accept")
            ->assertOk();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$firstOffer->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $availableArtisan = $this->createArtisan(['name' => 'Paul']);
        $secondRequest = $this->requestFor($client);
        $secondOffer = $this->pendingOffer($secondRequest, $availableArtisan);

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/repair-requests/{$secondRequest->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', RepairRequest::STATUS_CANCELLED);

        $this->assertDatabaseHas('repair_request_offers', [
            'id' => $secondOffer->id,
            'status' => RepairRequestOffer::STATUS_CANCELLED,
        ]);

        $this->actingAs($availableArtisan, 'sanctum')
            ->postJson("/api/v1/artisan/offers/{$secondOffer->id}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function user(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
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
            'title' => 'Fuite sous lâ€™Ã©vier',
            'description' => 'Un tuyau sâ€™est dÃ©tachÃ© et lâ€™eau coule sous lâ€™Ã©vier.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Rue derriÃ¨re la pharmacie, portail bleu.',
            'status' => RepairRequest::STATUS_PENDING,
        ], $overrides));

        $repairRequest->assignReference();

        return $repairRequest->fresh();
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
