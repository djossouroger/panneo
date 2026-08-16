<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class ArtisanVerificationGateApiTest extends TestCase
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

    public function test_pending_artisan_cannot_toggle_availability(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertForbidden()
            ->assertJsonPath('code', 'ARTISAN_NOT_VERIFIED');
    }

    public function test_rejected_artisan_cannot_toggle_availability(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_REJECTED]);

        $this->actingAs($artisan, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertForbidden()
            ->assertJsonPath('code', 'ARTISAN_NOT_VERIFIED');
    }

    public function test_verified_artisan_can_toggle_availability(): void
    {
        $artisan = $this->createArtisan();

        $this->actingAs($artisan, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);
    }

    public function test_pending_artisan_cannot_access_offers(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/artisan/offers')
            ->assertForbidden()
            ->assertJsonPath('code', 'ARTISAN_NOT_VERIFIED');
    }

    public function test_pending_artisan_cannot_access_repair_requests(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/artisan/repair-requests')
            ->assertForbidden()
            ->assertJsonPath('code', 'ARTISAN_NOT_VERIFIED');
    }

    public function test_pending_artisan_cannot_start_an_intervention(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $client = $this->user(['role' => 'client']);
        $repairRequest = $this->requestFor($client, [
            'status' => RepairRequest::STATUS_ACCEPTED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now(),
        ]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisan/repair-requests/{$repairRequest->id}/start")
            ->assertForbidden()
            ->assertJsonPath('code', 'ARTISAN_NOT_VERIFIED');
    }

    public function test_pending_artisan_can_still_prepare_profile_and_verification(): void
    {
        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->putJson('/api/v1/artisan/profile', [
                'description' => 'Préparation du profil en attente.',
                'years_of_experience' => 3,
                'specialties' => ['Urgences'],
            ])
            ->assertOk();

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/artisan/verification')
            ->assertOk();

        $this->actingAs($artisan, 'sanctum')
            ->putJson('/api/v1/artisan/working-hours', [
                'hours' => [
                    ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '18:00', 'is_working_day' => true],
                ],
            ])
            ->assertOk();
    }

    public function test_client_cannot_send_offer_to_pending_artisan(): void
    {
        $client = $this->user(['role' => 'client']);
        $pendingArtisan = $this->createArtisan();
        $pendingArtisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $request = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$request->id}/offers", ['artisan_id' => $pendingArtisan->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artisan_id');
    }

    public function test_pending_artisan_does_not_appear_in_matching(): void
    {
        $client = $this->user(['role' => 'client']);

        $pendingArtisan = $this->createArtisan();
        $pendingArtisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $verifiedArtisan = $this->createArtisan(['name' => 'Vérifié', 'email' => 'verifie@example.com']);

        $request = $this->requestFor($client);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($verifiedArtisan->id, $ids);
        $this->assertNotContains($pendingArtisan->id, $ids);
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
