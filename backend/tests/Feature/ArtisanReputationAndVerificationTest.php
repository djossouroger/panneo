<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Category;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithArtisans;
use Tests\TestCase;

class ArtisanReputationAndVerificationTest extends TestCase
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

    public function test_average_rating_is_calculated_correctly(): void
    {
        $client = $this->user(['role' => 'client', 'name' => 'Roger D.']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);

        $this->createCompletedRequest($client, $artisan, 5);
        $this->createCompletedRequest($client, $artisan, 4);
        $this->createCompletedRequest($client, $artisan, 5);

        $this->assertEqualsWithDelta(4.7, $artisan->averageRating(), 0.05);
        $this->assertSame(3, $artisan->reviewsCount());
    }

    public function test_average_rating_is_null_when_no_reviews(): void
    {
        $artisan = $this->createArtisan();

        $this->assertNull($artisan->averageRating());
        $this->assertSame(0, $artisan->reviewsCount());
    }

    public function test_new_artisan_displays_as_pending_on_public_profile(): void
    {
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $response = $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertOk();

        $response->assertJsonPath('data.profile.verification_status', ArtisanProfile::VERIFICATION_PENDING);
        $response->assertJsonPath('data.profile.verified_label', 'Profil non encore vérifié');
        $response->assertJsonPath('data.stats.reviews_count', 0);
        $response->assertJsonPath('data.stats.average_rating', null);
        $response->assertJsonPath('data.stats.completed_interventions', 0);
    }

    public function test_completed_interventions_count_reflected_in_stats(): void
    {
        $client = $this->user(['role' => 'client']);
        $artisan = $this->createArtisan();

        $this->createCompletedRequest($client, $artisan, 5);
        $this->createCompletedRequest($client, $artisan, 3);

        $response = $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertOk();

        $response->assertJsonPath('data.stats.completed_interventions', 2);
        $response->assertJsonPath('data.stats.reviews_count', 2);
    }

    public function test_artisan_submits_verification_and_status_becomes_pending(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', ArtisanVerificationSubmission::STATUS_PENDING);

        $this->assertSame(ArtisanProfile::VERIFICATION_PENDING, $artisan->artisanProfile->fresh()->verification_status);

        $submission = ArtisanVerificationSubmission::first();
        $this->assertSame(ArtisanVerificationSubmission::STATUS_PENDING, $submission->status);
        $this->assertSame(2, $submission->documents()->count());

        foreach ($submission->documents as $document) {
            $this->assertTrue(Storage::disk('local')->exists($document->file_path));
        }
    }

    public function test_verification_requires_identity_document_and_selfie(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', [
                'documents' => [
                    ['document_type' => 'identity_document', 'file' => UploadedFile::fake()->image('cni.jpg', 800, 600)],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', [
                'documents' => [
                    ['document_type' => 'selfie', 'file' => UploadedFile::fake()->image('selfie.jpg', 800, 600)],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents');

        $this->assertNull(ArtisanVerificationSubmission::first());
    }

    public function test_verification_rejects_invalid_image_and_oversized_file(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', [
                'documents' => [
                    ['document_type' => 'identity_document', 'file' => UploadedFile::fake()->create('cni.txt', 100, 'text/plain')],
                    ['document_type' => 'selfie', 'file' => UploadedFile::fake()->image('selfie.jpg')],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents.0.file');

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', [
                'documents' => [
                    ['document_type' => 'identity_document', 'file' => UploadedFile::fake()->create('cni.jpg', 6000, 'image/jpeg')],
                    ['document_type' => 'selfie', 'file' => UploadedFile::fake()->image('selfie.jpg')],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents.0.file');

        $this->assertNull(ArtisanVerificationSubmission::first());
    }

    public function test_verification_documents_are_private_and_protected(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);
        $client = $this->user(['role' => 'client']);
        $otherArtisan = $this->createArtisan(['name' => 'Autre']);
        $admin = $this->user(['role' => 'admin']);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated();

        $document = ArtisanVerificationSubmission::first()->documents()->first();

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/v1/artisan/verification/documents/{$document->id}")
            ->assertForbidden();

        $this->actingAs($otherArtisan, 'sanctum')
            ->getJson("/api/v1/artisan/verification/documents/{$document->id}")
            ->assertForbidden();

        $this->actingAs($artisan, 'sanctum')
            ->get("/api/v1/artisan/verification/documents/{$document->id}")
            ->assertOk();

        $this->actingAs($admin, 'web')
            ->get(route('admin.verifications.documents.download', $document))
            ->assertOk();

        $this->actingAs($admin, 'web')
            ->get(route('admin.verifications.documents.image', $document))
            ->assertOk();
    }

    public function test_artisan_cannot_submit_while_a_submission_is_pending(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $payload = $this->verificationPayload();

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $payload)
            ->assertCreated();

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $payload)
            ->assertUnprocessable();
    }

    public function test_artisan_can_cancel_pending_submission(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated();

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification/cancel')
            ->assertOk();

        $this->assertNull(ArtisanVerificationSubmission::first());
        $this->assertSame(ArtisanProfile::VERIFICATION_PENDING, $artisan->artisanProfile->fresh()->verification_status);
    }

    public function test_admin_approves_verification_and_notifies_artisan(): void
    {
        Storage::fake('local');

        $admin = $this->user(['role' => 'admin', 'name' => 'Admin']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated();

        $submission = ArtisanVerificationSubmission::first();

        $this->actingAs($admin, 'web')
            ->post(route('admin.verifications.approve', $submission))
            ->assertRedirect();

        $this->assertSame(ArtisanVerificationSubmission::STATUS_APPROVED, $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->reviewed_at);

        $profile = $artisan->artisanProfile->fresh();
        $this->assertSame(ArtisanProfile::VERIFICATION_VERIFIED, $profile->verification_status);
        $this->assertNotNull($profile->verified_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => Notification::TYPE_ARTISAN_ACCOUNT_VERIFIED,
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('admin.verifications'))
            ->assertOk()
            ->assertSee('Jean D.');
    }

    public function test_admin_can_view_verification_detail_with_images(): void
    {
        Storage::fake('local');

        $admin = $this->user(['role' => 'admin', 'name' => 'Admin']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated();

        $submission = ArtisanVerificationSubmission::first();

        $this->actingAs($admin, 'web')
            ->get(route('admin.verifications.show', $submission))
            ->assertOk()
            ->assertSee('Valider cet artisan ?')
            ->assertSee('Refuser le dossier')
            ->assertSee('Pièce d’identité')
            ->assertSee('Selfie avec pièce');
    }

    public function test_admin_rejects_verification_with_reason(): void
    {
        Storage::fake('local');

        $admin = $this->user(['role' => 'admin', 'name' => 'Admin']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', $this->verificationPayload())
            ->assertCreated();

        $submission = ArtisanVerificationSubmission::first();

        $this->actingAs($admin, 'web')
            ->post(route('admin.verifications.reject', $submission), ['reason' => 'Document illisible'])
            ->assertRedirect();

        $this->assertSame(ArtisanVerificationSubmission::STATUS_REJECTED, $submission->fresh()->status);
        $this->assertSame('Document illisible', $submission->fresh()->rejection_reason);
        $this->assertSame(ArtisanProfile::VERIFICATION_REJECTED, $artisan->artisanProfile->fresh()->verification_status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $artisan->id,
            'type' => Notification::TYPE_VERIFICATION_REJECTED,
        ]);

        $this->actingAs($artisan, 'sanctum')
            ->getJson('/api/v1/artisan/verification')
            ->assertOk()
            ->assertJsonPath('data.verification_status', ArtisanProfile::VERIFICATION_REJECTED)
            ->assertJsonPath('data.submission.rejection_reason', 'Document illisible');
    }

    public function test_rejected_artisan_can_resubmit_and_becomes_pending_again(): void
    {
        Storage::fake('local');

        $artisan = $this->createArtisan();
        $profile = $artisan->artisanProfile;
        $profile->forceFill(['verification_status' => ArtisanProfile::VERIFICATION_REJECTED])->save();

        $this->actingAs($artisan, 'sanctum')
            ->postJson('/api/v1/artisan/verification', [
                'documents' => [
                    ['document_type' => 'identity_document', 'file' => UploadedFile::fake()->image('cni.jpg', 800, 600)],
                    ['document_type' => 'selfie', 'file' => UploadedFile::fake()->image('selfie.jpg', 800, 600)],
                    ['document_type' => 'professional_proof', 'file' => UploadedFile::fake()->create('diplome.pdf', 100, 'application/pdf')],
                ],
            ])
            ->assertCreated();

        $this->assertSame(ArtisanProfile::VERIFICATION_PENDING, $artisan->artisanProfile->fresh()->verification_status);
    }

    public function test_admin_verification_shows_on_public_profile(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $artisan = $this->createArtisan(['name' => 'Jean D.']);
        $artisan->artisanProfile()->update([
            'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertOk();

        $response->assertJsonPath('data.profile.verification_status', ArtisanProfile::VERIFICATION_VERIFIED);
        $response->assertJsonPath('data.profile.verified_label', 'Vérifié par Pannéo');
    }

    public function test_public_profile_returns_correct_structure(): void
    {
        $artisan = $this->createArtisan(['name' => 'Jean D.']);

        $response = $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'profile' => [
                    'categories',
                    'city',
                    'district',
                    'description',
                    'is_available',
                    'verification_status',
                    'verified_label',
                    'years_of_experience',
                    'specialties',
                    'service_areas',
                    'portfolio',
                ],
                'stats' => [
                    'completed_interventions',
                    'average_rating',
                    'reviews_count',
                ],
            ],
        ]);
    }

    public function test_public_profile_does_not_expose_private_info(): void
    {
        $artisan = $this->createArtisan(['name' => 'Jean D.', 'email' => 'jean@example.com', 'phone' => '+229 61 00 00 01']);

        $response = $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertOk();

        $response->assertJsonMissing(['email' => 'jean@example.com']);
        $response->assertJsonMissing(['phone' => '+229 61 00 00 01']);
    }

    public function test_public_profile_returns_404_for_non_artisan(): void
    {
        $client = $this->user(['role' => 'client']);

        $this->getJson("/api/v1/artisans/{$client->id}")
            ->assertNotFound();
    }

    public function test_public_profile_returns_404_for_inactive_artisan(): void
    {
        $artisan = $this->createArtisan(['is_active' => false]);

        $this->getJson("/api/v1/artisans/{$artisan->id}")
            ->assertNotFound();
    }

    public function test_artisan_cannot_self_verify_via_api(): void
    {
        $artisan = $this->createArtisan();
        $profile = $artisan->artisanProfile;
        $profile->forceFill(['verification_status' => ArtisanProfile::VERIFICATION_PENDING])->save();

        $this->actingAs($artisan, 'sanctum')
            ->putJson('/api/v1/artisan/profile', [
                'description' => 'Test',
                'years_of_experience' => 5,
                'specialties' => ['Urgences', 'Sanitaire'],
            ])
            ->assertOk();

        $profile = $artisan->artisanProfile->fresh();
        $this->assertSame(ArtisanProfile::VERIFICATION_PENDING, $profile->verification_status);
        $this->assertSame(5, $profile->years_of_experience);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function verificationPayload(): array
    {
        return [
            'documents' => [
                ['document_type' => 'identity_document', 'file' => UploadedFile::fake()->image('cni.jpg', 800, 600)],
                ['document_type' => 'selfie', 'file' => UploadedFile::fake()->image('selfie.jpg', 800, 600)],
            ],
        ];
    }

    private function createCompletedRequest(User $client, User $artisan, int $rating): RepairRequest
    {
        $repairRequest = RepairRequest::create([
            'client_id' => $client->id,
            'category_id' => $this->plumbing->id,
            'title' => 'Fuite',
            'description' => 'Une fuite sous l’évier.',
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
            'rating' => $rating,
            'comment' => 'Bon dépannage.',
        ]);

        return $repairRequest->fresh();
    }
}
