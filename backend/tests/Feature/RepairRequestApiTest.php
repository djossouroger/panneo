<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Plomberie',
            'slug' => 'plomberie',
            'icon' => 'plumbing',
            'is_active' => true,
        ]);
    }

    public function test_client_can_create_a_repair_request(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/repair-requests', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.category.slug', 'plomberie');

        $this->assertMatchesRegularExpression('/^PAN-\d{4}-\d{6}$/', $response->json('data.reference'));
        $this->assertDatabaseHas('repair_requests', [
            'client_id' => $client->id,
            'category_id' => $this->category->id,
            'status' => 'pending',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
        ]);
    }

    public function test_artisan_cannot_create_a_client_repair_request(): void
    {
        $artisan = User::factory()->create(['role' => 'artisan']);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/repair-requests', $this->payload())
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_blocked(): void
    {
        $this->postJson('/api/v1/repair-requests', $this->payload())
            ->assertUnauthorized();
    }

    public function test_inactive_client_is_blocked(): void
    {
        $client = User::factory()->create(['role' => 'client', 'is_active' => false]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/repair-requests', $this->payload())
            ->assertForbidden();
    }

    public function test_inactive_category_is_rejected(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $inactive = Category::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'icon' => 'wrench',
            'is_active' => false,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/repair-requests', $this->payload(['category_id' => $inactive->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_required_fields_are_validated(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/repair-requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'description', 'city', 'district']);
    }

    public function test_client_can_attach_up_to_two_photos(): void
    {
        Storage::fake('public');
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client, 'sanctum')
            ->post('/api/v1/repair-requests', $this->payload([
                'images' => [
                    UploadedFile::fake()->image('fuite.jpg', 600, 400),
                    UploadedFile::fake()->image('tuyau.png', 600, 400),
                ],
            ]))
            ->assertCreated();

        $this->assertCount(2, $response->json('data.images'));
        $this->assertStringContainsString('/storage/request-images/', $response->json('data.images.0'));
        $this->assertCount(2, Storage::disk('public')->files('request-images'));
    }

    public function test_more_than_two_photos_are_rejected(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->post('/api/v1/repair-requests', $this->payload([
                'images' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                    UploadedFile::fake()->image('c.jpg'),
                ],
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images');
    }

    public function test_non_image_attachment_is_rejected(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->post('/api/v1/repair-requests', $this->payload([
                'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0');
    }

    public function test_client_sees_only_own_requests_sorted_latest_first(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $otherClient = User::factory()->create(['role' => 'client']);
        $older = $this->requestFor($client, ['title' => 'Ancienne', 'created_at' => now()->subDay()]);
        $newer = $this->requestFor($client, ['title' => 'Nouvelle', 'created_at' => now()]);
        $this->requestFor($otherClient, ['title' => 'Autre client']);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/repair-requests')
            ->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertSame($newer->id, $response->json('data.0.id'));
        $this->assertSame($older->id, $response->json('data.1.id'));
    }

    public function test_owner_can_view_request_detail(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.reference', $repairRequest->reference);
    }

    public function test_other_client_cannot_view_or_cancel_request(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $repairRequest = $this->requestFor($owner);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$repairRequest->id}")
            ->assertForbidden();

        $this->actingAs($other, 'sanctum')
            ->patchJson("/api/v1/repair-requests/{$repairRequest->id}/cancel")
            ->assertForbidden();
    }

    public function test_pending_request_can_be_cancelled_once(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $repairRequest = $this->requestFor($client);

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/repair-requests/{$repairRequest->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('repair_requests', [
            'id' => $repairRequest->id,
            'status' => 'cancelled',
        ]);

        $this->actingAs($client, 'sanctum')
            ->patchJson("/api/v1/repair-requests/{$repairRequest->id}/cancel")
            ->assertUnprocessable();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Fuite sous l’évier',
            'description' => 'Un tuyau s’est détaché et l’eau coule sous l’évier.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Rue derrière la pharmacie, portail bleu.',
        ], $overrides);
    }

    private function requestFor(User $client, array $overrides = []): RepairRequest
    {
        $repairRequest = RepairRequest::create(array_merge([
            'client_id' => $client->id,
            'category_id' => $this->category->id,
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
}