<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_code_for_existing_user(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('verification_codes', [
            'user_id' => null,
            'purpose' => VerificationCode::PURPOSE_PASSWORD_RESET,
            'channel' => 'email',
            'recipient' => $user->email,
        ]);
    }

    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu@example.com'])
            ->assertOk();

        $this->assertDatabaseCount('verification_codes', 0);
    }

    public function test_password_reset_with_valid_code_works(): void
    {
        $user = $this->user();

        $this->seedResetOtp($user->email, '123456');

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NouveauPass123!', $user->fresh()->password));

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'NouveauPass123!',
        ])->assertOk();

        $this->assertDatabaseHas('security_audit_logs', [
            'user_id' => $user->id,
            'event' => 'password_reset',
        ]);
    }

    public function test_password_reset_with_wrong_code_is_rejected(): void
    {
        $user = $this->user();
        $this->seedResetOtp($user->email, '123456');

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => '999999',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ])->assertUnprocessable();

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_reset_code_is_single_use(): void
    {
        $user = $this->user();
        $this->seedResetOtp($user->email, '123456');

        $payload = [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

        $this->postJson('/api/v1/auth/password/reset', array_merge($payload, ['password' => 'AutrePass123!', 'password_confirmation' => 'AutrePass123!']))
            ->assertUnprocessable();

        $this->assertTrue(Hash::check('NouveauPass123!', $user->fresh()->password));
    }

    public function test_password_reset_logs_out_other_sessions(): void
    {
        $user = $this->user();
        $this->seedResetOtp($user->email, '123456');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->postJson('/api/v1/auth/password/reset', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function seedResetOtp(string $recipient, string $code): void
    {
        VerificationCode::create([
            'user_id' => null,
            'purpose' => VerificationCode::PURPOSE_PASSWORD_RESET,
            'channel' => 'email',
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'last_sent_at' => now()->subSeconds(61),
        ]);
    }
}

