<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase, InteractsWithArtisans;

    private Category $plumbing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plumbing = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => 'plumbing', 'is_active' => true]);
    }

    public function test_client_can_favorite_artisan_after_completed_intervention(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $this->completedRequest($client, $artisan);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/artisans/{$artisan->id}/favorite", ['favorite' => true])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('favorite_artisans', [
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
        ]);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/artisans/{$artisan->id}/favorite")
            ->assertOk()
            ->assertJsonPath('data.is_favorite', true);
    }

    public function test_client_cannot_favorite_without_completed_intervention(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/artisans/{$artisan->id}/favorite", ['favorite' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('artisan_id');
    }

    public function test_client_can_unfavorite(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();
        $this->completedRequest($client, $artisan);

        $client->favoriteArtisans()->attach($artisan->id);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/artisans/{$artisan->id}/favorite", ['favorite' => false])
            ->assertOk()
            ->assertJsonPath('data.is_favorite', false);

        $this->assertDatabaseMissing('favorite_artisans', [
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
        ]);
    }

    public function test_artisan_cannot_favorite(): void
    {
        $artisan = $this->createArtisan();

        $this->actingAs($artisan, 'sanctum')
            ->postJson("/api/v1/artisans/{$artisan->id}/favorite", ['favorite' => true])
            ->assertForbidden();
    }

    public function test_favorites_index_returns_list_with_stats(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $this->completedRequest($client, $artisan);

        $client->favoriteArtisans()->attach($artisan->id);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/favorites')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Jean D.', $response->json('data.0.name'));
        $this->assertArrayHasKey('stats', $response->json('data.0'));
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function completedRequest(User $client, User $artisan): RepairRequest
    {
        $repairRequest = RepairRequest::create([
            'client_id' => $client->id,
            'category_id' => $this->plumbing->id,
            'title' => 'Fuite',
            'description' => 'Une fuite.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'status' => RepairRequest::STATUS_COMPLETED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(20),
            'completed_at' => now()->subMinutes(10),
        ]);

        $repairRequest->assignReference();

        Review::create([
            'repair_request_id' => $repairRequest->id,
            'client_id' => $client->id,
            'artisan_id' => $artisan->id,
            'rating' => 5,
            'comment' => 'Parfait.',
        ]);

        return $repairRequest;
    }
}

