<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class ReviewApiTest extends TestCase
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

    public function test_client_owner_of_completed_request_can_leave_review(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertCreated()
            ->assertJsonPath('data.review.rating', 5);

        $this->assertDatabaseHas('reviews', [
            'repair_request_id' => $repairRequest->id,
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'rating' => 5,
        ]);
    }

    public function test_client_cannot_review_pending_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->requestFor($client, ['status' => 'awaiting_artisan', 'accepted_artisan_id' => $artisan->id]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_client_cannot_review_accepted_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->acceptedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_client_cannot_review_in_progress_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->acceptedRequest($client, $artisan, [
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_other_client_cannot_review(): void
    {
        $owner = $this->user(['role' => 'client', 'name' => 'Roger']);
        $other = $this->user(['role' => 'client', 'name' => 'Paul']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($owner, $artisan);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_artisan_cannot_review(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_double_review_is_forbidden(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertCreated();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('review');
    }

    public function test_rating_zero_is_rejected(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_rating_six_is_rejected(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_rating_must_be_integer(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 4.5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');
    }

    public function test_comment_is_optional(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 3])
            ->assertCreated();

        $this->assertDatabaseHas('reviews', [
            'repair_request_id' => $repairRequest->id,
            'rating' => 3,
            'comment' => null,
        ]);
    }

    public function test_comment_max_500_chars(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", [
                'rating' => 4,
                'comment' => str_repeat('x', 501),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');
    }

    public function test_unique_constraint_on_repair_request(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        Review::create([
            'repair_request_id' => $repairRequest->id,
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'rating' => 5,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 4])
            ->assertUnprocessable();
    }

    public function test_unauthenticated_user_cannot_review(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertUnauthorized();
    }

    public function test_client_can_view_existing_review(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $repairRequest = $this->completedRequest($client, $artisan);

        Review::create([
            'repair_request_id' => $repairRequest->id,
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'rating' => 5,
            'comment' => 'Intervention rapide et professionnelle.',
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/review")
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Intervention rapide et professionnelle.')
            ->assertJsonPath('data.artisan.name', 'Jean D.');
    }

    public function test_client_without_review_sees_null(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/review")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_client_cannot_view_other_clients_review(): void
    {
        $owner = $this->user(['role' => 'client', 'name' => 'Roger']);
        $other = $this->user(['role' => 'client', 'name' => 'Paul']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($owner, $artisan);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}/review")
            ->assertForbidden();
    }

    public function test_artisan_cannot_create_review(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", ['rating' => 5])
            ->assertForbidden();
    }

    public function test_artisan_is_prevented_from_self_review_via_direct_payload(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $repairRequest = $this->completedRequest($client, $artisan);

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/repair-requests/{$repairRequest->id}/review", [
                'rating' => 5,
                'client_id' => $artisan->id,
            ])
            ->assertForbidden();
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
        return $this->requestFor($client, array_merge([
            'status' => RepairRequest::STATUS_ACCEPTED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subMinutes(10),
        ], $overrides));
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
}
