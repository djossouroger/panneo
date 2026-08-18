<?php

namespace Tests\Feature;

use App\Models\ArtisanProfile;
use App\Models\Category;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Category::create([
            'name' => 'Plomberie',
            'slug' => 'plomberie',
            'icon' => 'plumbing',
            'is_active' => true,
        ]);
    }

    public function test_registers_a_client_account(): void
    {
        $payload = [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'phone' => '+33600000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'client',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'client@example.com', 'role' => 'client']);
    }

    public function test_registration_auto_verifies_email_in_log_mode(): void
    {
        $payload = [
            'name' => 'Client Vérif',
            'email' => 'verify@example.com',
            'phone' => '+33600000009',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'client',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertOk()
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonPath('data.requires_email_verification', false);

        $this->assertNotNull(User::where('email', 'verify@example.com')->firstOrFail()->email_verified_at);

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_login_is_blocked_until_email_is_verified(): void
    {
        $this->createUser([
            'email' => 'unverified@example.com',
            'phone' => '+33600000010',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ])->assertStatus(403)
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_email_verify_send_auto_verifies_in_log_mode(): void
    {
        $user = $this->createUser([
            'email' => 'pending@example.com',
            'phone' => '+33600000011',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/auth/email-verify/send', ['email' => 'pending@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'Password123!',
        ])->assertOk();
    }

    public function test_email_verification_rejects_a_wrong_code(): void
    {
        $user = $this->createUser([
            'email' => 'wrong@example.com',
            'phone' => '+33600000012',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        $this->seedEmailOtp('wrong@example.com', '123456');

        $this->postJson('/api/v1/auth/email-verify/confirm', [
            'email' => 'wrong@example.com',
            'code' => '999999',
        ])->assertUnprocessable();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_send_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/v1/auth/email-verify/send', ['email' => 'inconnu@example.com'])
            ->assertOk();

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_registers_an_artisan_account_and_creates_a_profile(): void
    {
        $category = Category::query()->firstOrFail();

        $payload = [
            'name' => 'Artisan Test',
            'email' => 'artisan@example.com',
            'phone' => '+33600000001',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'artisan',
            'category_id' => $category->id,
            'city' => 'Paris',
            'district' => '10e',
        ];

        $response = $this->postJson('/api/v1/auth/register', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'artisan@example.com', 'role' => 'artisan']);
        $this->assertDatabaseHas('artisan_profiles', ['city' => 'Paris', 'is_available' => false]);
    }

    public function test_rejects_public_admin_registration(): void
    {
        $payload = [
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'phone' => '+33600000002',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'admin',
        ];

        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(422);
    }

    public function test_logs_in_a_valid_user_and_returns_current_profile(): void
    {
        $this->createUser([
            'email' => 'login@example.com',
            'phone' => '+33600000003',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'login@example.com');

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_rejects_invalid_credentials(): void
    {
        $this->createUser([
            'email' => 'wrong@example.com',
            'phone' => '+33600000004',
            'password' => bcrypt('CorrectPassword!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@example.com',
            'password' => 'WrongPass!',
        ])->assertStatus(401);
    }

    public function test_blocks_inactive_accounts(): void
    {
        $this->createUser([
            'email' => 'inactive@example.com',
            'phone' => '+33600000005',
            'password' => bcrypt('Password123!'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'Password123!',
        ])->assertStatus(403);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->createUser([
            'email' => 'logout@example.com',
            'phone' => '+33600000006',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_allows_artisan_availability_updates(): void
    {
        $user = $this->createUser([
            'role' => 'artisan',
            'phone' => '+33600000007',
            'password' => bcrypt('Password123!'),
        ]);

        $user->artisanProfile()->create([
            'category_id' => Category::query()->firstOrFail()->id,
            'city' => 'Lyon',
            'is_available' => false,
            'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertOk();

        $this->assertDatabaseHas('artisan_profiles', ['user_id' => $user->id, 'is_available' => true]);
    }

    public function test_client_cannot_call_artisan_routes(): void
    {
        $user = $this->createUser([
            'role' => 'client',
            'phone' => '+33600000008',
            'password' => bcrypt('Password123!'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/artisan/availability', ['is_available' => true])
            ->assertForbidden();
    }

    private function createUser(array $attributes = []): User
    {
        /** @var User $user */
        $user = User::factory()->create($attributes);

        return $user;
    }

    private function seedEmailOtp(string $recipient, string $code): void
    {
        VerificationCode::create([
            'user_id' => null,
            'purpose' => VerificationCode::PURPOSE_EMAIL_VERIFY,
            'channel' => 'email',
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'last_sent_at' => now()->subSeconds(61),
        ]);
    }
}