<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRepairRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_repair_requests_dashboard_list_detail_and_matching_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client', 'name' => 'Client Admin', 'email' => 'client-admin@example.com']);
        $artisan = User::factory()->create(['role' => 'artisan', 'name' => 'Jean D.', 'phone' => '+229 61 00 00 01']);
        $category = Category::create([
            'name' => 'Plomberie',
            'slug' => 'plomberie',
            'icon' => 'plumbing',
            'is_active' => true,
        ]);
        ArtisanProfile::create([
            'user_id' => $artisan->id,
            'category_id' => $category->id,
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'description' => 'Plombier disponible.',
            'is_available' => true,
        ]);

        $pendingRequest = $this->requestFor($client, $category, [
            'title' => 'Fuite sous l’évier',
            'status' => RepairRequest::STATUS_PENDING,
        ]);

        $inProgressRequest = $this->requestFor($client, $category, [
            'title' => 'Robinet cassé',
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subHour(),
            'started_at' => now()->subMinutes(30),
        ]);

        $completedRequest = $this->requestFor($client, $category, [
            'title' => 'Siphon bouché',
            'status' => RepairRequest::STATUS_COMPLETED,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subHours(2),
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(10),
        ]);

        RepairRequestOffer::create([
            'repair_request_id' => $completedRequest->id,
            'artisan_id' => $artisan->id,
            'status' => RepairRequestOffer::STATUS_ACCEPTED,
            'responded_at' => $completedRequest->accepted_at,
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('En recherche')
            ->assertSee('En attente de réponse')
            ->assertSee('Interventions en cours')
            ->assertSee('Terminées')
            ->assertSee('Artisans disponibles');

        $this->actingAs($admin)
            ->get('/admin/repair-requests')
            ->assertOk()
            ->assertSee($pendingRequest->reference)
            ->assertSee($inProgressRequest->reference)
            ->assertSee($completedRequest->reference)
            ->assertSee('Client Admin')
            ->assertSee('En recherche')
            ->assertSee('Intervention en cours')
            ->assertSee('Terminée')
            ->assertSee('Jean D.');

        $this->actingAs($admin)
            ->get("/admin/repair-requests/{$completedRequest->id}")
            ->assertOk()
            ->assertSee($completedRequest->reference)
            ->assertSee('client-admin@example.com')
            ->assertSee('Mise en relation')
            ->assertSee('Artisan accepté')
            ->assertSee('Intervention commencée')
            ->assertSee('Intervention terminée')
            ->assertSee('Terminée')
            ->assertSee('Jean D.');
    }

    public function test_non_admin_cannot_access_repair_requests_backoffice(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)
            ->get('/admin/repair-requests')
            ->assertForbidden();
    }

    private function requestFor(User $client, Category $category, array $overrides = []): RepairRequest
    {
        $repairRequest = RepairRequest::create(array_merge([
            'client_id' => $client->id,
            'category_id' => $category->id,
            'title' => 'Demande admin',
            'description' => 'Description de test.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'address_details' => 'Portail bleu.',
            'status' => RepairRequest::STATUS_PENDING,
        ], $overrides));

        $repairRequest->assignReference();

        return $repairRequest->fresh();
    }
}
