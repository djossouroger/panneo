<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountSecurityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_otp_send_and_verify_flow(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/phone/send-code', ['phone' => '+229 61 00 00 01'])
            ->assertOk()
            ->assertJsonPath('data.resend_after', 60);

        $this->seedOtp($user, VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', '+229 61 00 00 01', '123456');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/phone/verify', ['phone' => '+229 61 00 00 01', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified', true);

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'phone_verified',
        ]);
    }

    public function test_phone_otp_with_wrong_code_is_rejected(): void
    {
        $user = $this->user();
        $this->seedOtp($user, VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', '+229 61 00 00 01', '123456');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/phone/verify', ['phone' => '+229 61 00 00 01', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_resend_is_rate_limited(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/phone/send-code', ['phone' => '+229 61 00 00 01'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/phone/resend', ['phone' => '+229 61 00 00 01'])
            ->assertUnprocessable();
    }

    public function test_client_can_upload_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user, 'sanctum')
            ->post('/api/v1/account/profile-photo', [
                'photo' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($user->fresh()->profile_photo_path);
        $this->assertCount(1, Storage::disk('public')->files('profile-photos'));
        $this->assertStringContainsString('/storage/profile-photos/', $user->fresh()->profile_photo_url);
    }

    public function test_email_change_requires_otp_to_new_address(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/email/send-code', ['new_email' => 'nouveau@example.com'])
            ->assertOk();

        $this->seedOtp($user, VerificationCode::PURPOSE_EMAIL_CHANGE, 'email', 'nouveau@example.com', '654321');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/email', ['new_email' => 'nouveau@example.com', 'code' => '654321'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'nouveau@example.com');

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'email_changed',
        ]);
    }

    public function test_email_change_with_wrong_code_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/email/send-code', ['new_email' => 'nouveau2@example.com'])
            ->assertOk();

        $this->seedOtp($user, VerificationCode::PURPOSE_EMAIL_CHANGE, 'email', 'nouveau2@example.com', '111111');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/email', ['new_email' => 'nouveau2@example.com', 'code' => '000000'])
            ->assertUnprocessable();

        $this->assertNotSame('nouveau2@example.com', $user->fresh()->email);
    }

    public function test_phone_change_requires_otp(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/phone/send-code', ['new_phone' => '+229 97 00 00 00'])
            ->assertOk();

        $this->seedOtp($user, VerificationCode::PURPOSE_PHONE_CHANGE, 'sms', '+229 97 00 00 00', '222222');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/account/phone', ['new_phone' => '+229 97 00 00 00', 'code' => '222222'])
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+229 97 00 00 00');

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'phone_changed',
        ]);
    }

    public function test_sessions_listed_and_revokable(): void
    {
        $user = $this->user();
        $mobile = $user->createToken('mobile');

        $other = $user->createToken('android');

        $this->withToken($mobile->plainTextToken)
            ->getJson('/api/v1/account/sessions')
            ->assertOk()
            ->assertJsonCount(2, 'data.sessions');

        $this->withToken($mobile->plainTextToken)
            ->deleteJson("/api/v1/account/sessions/{$other->accessToken->id}")
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_current_session_cannot_be_revoked(): void
    {
        $user = $this->user();
        $current = $user->createToken('mobile');

        $this->withToken($current->plainTextToken)
            ->deleteJson("/api/v1/account/sessions/{$current->accessToken->id}")
            ->assertUnprocessable();
    }

    public function test_revoke_other_sessions_requires_password(): void
    {
        $user = $this->user();
        $current = $user->createToken('mobile');
        $user->createToken('android');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/account/sessions/others', ['password' => 'wrong'])
            ->assertUnprocessable();

        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/account/sessions/others', ['password' => 'password'])
            ->assertOk();

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_delete_account_requires_password_and_logs_out(): void
    {
        $user = $this->user();
        $current = $user->createToken('mobile');

        $this->withToken($current->plainTextToken)
            ->postJson('/api/v1/account/delete', ['password' => 'password'])
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'account_deleted',
        ]);
    }

    public function test_delete_account_is_blocked_during_active_intervention(): void
    {
        $client = $this->user();
        $artisan = $this->user(['role' => 'artisan', 'email' => 'artisan@example.com']);

        RepairRequest::create([
            'client_id' => $client->id,
            'category_id' => $this->categoryId(),
            'title' => 'Fuite',
            'description' => 'Fuite.',
            'city' => 'Cotonou',
            'district' => 'Akpakpa',
            'status' => RepairRequest::STATUS_IN_PROGRESS,
            'accepted_artisan_id' => $artisan->id,
            'accepted_at' => now()->subHour(),
            'started_at' => now()->subMinutes(30),
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/v1/account/delete', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertDatabaseHas('users', ['id' => $client->id, 'deleted_at' => null]);
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function categoryId(): int
    {
        return \App\Models\Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => 'plumbing', 'is_active' => true])->id;
    }

    private function seedOtp(User $user, string $purpose, string $channel, string $recipient, string $code): void
    {
        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now()->subSeconds(61),
        ]);
    }
}

