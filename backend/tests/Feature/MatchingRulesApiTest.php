<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\ArtisanUnavailability;
use App\Models\Category;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class MatchingRulesApiTest extends TestCase
{
    use RefreshDatabase, InteractsWithArtisans;

    private Category $plumbing;
    private Category $electricity;
    private Category $climate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plumbing = Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => 'plumbing', 'is_active' => true]);
        $this->electricity = Category::create(['name' => 'Électricité', 'slug' => 'electricite', 'icon' => 'electricity', 'is_active' => true]);
        $this->climate = Category::create(['name' => 'Climatisation', 'slug' => 'climatisation', 'icon' => 'climate', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_multi_category_artisan_is_offered_for_both_trades(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->categories()->syncWithoutDetaching([
            $this->electricity->id => ['is_primary' => false],
        ]);

        $request = $this->requestFor($client, ['category_id' => $this->electricity->id]);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($artisan->id, $ids);

        $categories = collect($response->json('data'))->firstWhere('id', $artisan->id)['categories'];
        $this->assertCount(2, $categories);
    }

    public function test_zone_matching_with_null_district_covers_whole_city(): void
    {
        $client = $this->user(['role' => 'client']);

        $wholeCity = $this->createArtisan(['name' => 'Ville entière'], ['district' => null]);
        $specificDistrict = $this->createArtisan(['name' => 'Quartier précis'], ['district' => 'Akpakpa']);
        $otherDistrict = $this->createArtisan(['name' => 'Autre quartier'], ['district' => 'Fidjrossè']);
        $otherCity = $this->createArtisan(['name' => 'Porto-Novo'], ['city' => 'Porto-Novo']);

        $request = $this->requestFor($client, ['district' => 'Agla']);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($wholeCity->id, $ids);
        $this->assertNotContains($specificDistrict->id, $ids);
        $this->assertNotContains($otherDistrict->id, $ids);
        $this->assertNotContains($otherCity->id, $ids);
    }

    public function test_zone_matching_is_case_insensitive_and_trimmed(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan(['name' => 'Jean D.'], ['city' => 'cotonou', 'district' => 'akpakpa']);

        $request = $this->requestFor($client, ['city' => '  Cotonou ', 'district' => 'AKPAKPA']);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $this->assertContains($artisan->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_artisan_outside_working_hours_is_not_offered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 15:00:00'));

        $client = $this->user(['role' => 'client']);

        $availableNow = $this->createArtisan(['name' => 'Dispo maintenant']);
        $availableNow->workingHours()->create(['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '18:00', 'is_working_day' => true]);

        $closed = $this->createArtisan(['name' => 'Fermé']);
        $closed->workingHours()->create(['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '12:00', 'is_working_day' => true]);

        $notWorkingDay = $this->createArtisan(['name' => 'Jour off']);
        $notWorkingDay->workingHours()->create(['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '18:00', 'is_working_day' => false]);

        $request = $this->requestFor($client);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($availableNow->id, $ids);
        $this->assertNotContains($closed->id, $ids);
        $this->assertNotContains($notWorkingDay->id, $ids);
    }

    public function test_artisan_with_active_unavailability_is_not_offered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 15:00:00'));

        $client = $this->user(['role' => 'client']);

        $unavailable = $this->createArtisan(['name' => 'En congé']);
        $unavailable->unavailabilities()->create([
            'type' => ArtisanUnavailability::TYPE_LEAVE,
            'starts_at' => Carbon::parse('2026-08-15 08:00:00'),
            'ends_at' => Carbon::parse('2026-08-18 18:00:00'),
            'reason' => 'Congé',
        ]);

        $available = $this->createArtisan(['name' => 'Disponible']);

        $request = $this->requestFor($client);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($unavailable->id, $ids);
        $this->assertContains($available->id, $ids);
    }

    public function test_artisan_with_active_intervention_is_not_offered(): void
    {
        $client = $this->user(['role' => 'client']);
        $busy = $this->createArtisan(['name' => 'Occupé']);

        $this->requestFor($client, [
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'accepted_artisan_id' => $busy->id,
            'accepted_at' => now()->subHour(),
            'started_at' => now()->subMinutes(30),
        ]);

        $otherClient = $this->user(['role' => 'client', 'email' => 'other2@example.com']);
        $request = $this->requestFor($otherClient);

        $response = $this->actingAs($otherClient, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($busy->id, $ids);
    }

    public function test_pending_artisans_are_not_offered(): void
    {
        $client = $this->user(['role' => 'client']);

        $pending = $this->createArtisan(['name' => 'Pending', 'email' => 'pending@example.com']);
        $pending->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $verified = $this->createArtisan(['name' => 'Verified', 'email' => 'verified@example.com']);

        $request = $this->requestFor($client);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $data = collect($response->json('data'));
        $this->assertContains($verified->id, $data->pluck('id')->all());
        $this->assertNotContains($pending->id, $data->pluck('id')->all());
    }

    public function test_rejected_artisan_is_never_offered(): void
    {
        $client = $this->user(['role' => 'client']);
        $rejected = $this->createArtisan(['name' => 'Rejeté']);
        $rejected->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_REJECTED]);

        $request = $this->requestFor($client);

        $response = $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/repair-requests/{$request->id}/available-artisans")
            ->assertOk();

        $this->assertNotContains($rejected->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_availability_toggle_reports_hors_horaires(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 15:00:00'));

        $artisan = $this->createArtisan();
        $artisan->workingHours()->create(['day_of_week' => 6, 'start_time' => '08:00', 'end_time' => '12:00', 'is_working_day' => true]);

        $this->actingAs($artisan, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertOk()
            ->assertJsonPath('data.is_available', true)
            ->assertJsonPath('data.hors_horaires', true)
            ->assertJsonPath('data.within_working_hours', false);
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
