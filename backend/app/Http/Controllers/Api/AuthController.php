<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['client', 'artisan'])],
            'category_id' => ['required_if:role,artisan', 'nullable', 'exists:categories,id'],
            'city' => ['required_if:role,artisan', 'nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        if ($user->role === 'artisan') {
            $profile = ArtisanProfile::create([
                'user_id' => $user->id,
                'category_id' => $data['category_id'],
                'city' => $data['city'],
                'district' => $data['district'] ?? null,
                'is_available' => false,
            ]);

            $user->categories()->attach($data['category_id'], ['is_primary' => true]);

            $user->serviceAreas()->create([
                'city' => $data['city'],
                'district' => $data['district'] ?? null,
            ]);

            $user->setRelation('artisanProfile', $profile);
        }

        $user->load('artisanProfile.categories');
        $token = $user->createToken('mobile')->plainTextToken;

        try {
            $this->otp->sendResetCode($user->email, VerificationCode::PURPOSE_EMAIL_VERIFY, 'email');
        } catch (\Throwable $e) {
            Log::warning(sprintf('Échec de l\'envoi du code de vérification e-mail pour %s : %s', $user->email, $e->getMessage()));
        }

        return response()->json([
            'success' => true,
            'message' => 'Compte créé avec succès.',
            'data' => [
                'user' => $user->toArray(),
                'token' => $token,
                'email_verified' => false,
                'requires_email_verification' => true,
            ],
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->record($user?->id, 'login_failed', ['email' => $credentials['email']]);

            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects.',
                'errors' => ['email' => ['Identifiants invalides.']],
            ], 401);
        }

        if (! $user->is_active) {
            $this->audit->record($user->id, 'login_failed', ['reason' => 'inactive']);

            return response()->json([
                'success' => false,
                'message' => 'Ce compte a été désactivé.',
                'errors' => ['account' => ['Compte désactivé.']],
            ], 403);
        }

        if (! $user->email_verified_at) {
            $this->audit->record($user->id, 'login_failed', ['reason' => 'email_not_verified']);

            return response()->json([
                'success' => false,
                'message' => 'Vérifiez votre adresse e-mail avant de vous connecter.',
                'code' => 'EMAIL_NOT_VERIFIED',
                'errors' => ['account' => ['Adresse e-mail non vérifiée.']],
                'data' => ['email' => $user->email],
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->audit->record($user->id, 'login_success');

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'data' => [
                'user' => $user->load('artisanProfile.categories')->toArray(),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();

        $this->audit->record($user->id, 'logout');

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
            'data' => [],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('artisanProfile.categories');

        return response()->json([
            'success' => true,
            'message' => 'Profil récupéré.',
            'data' => $user,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user) {
            try {
                $this->otp->sendResetCode($user->email, \App\Models\VerificationCode::PURPOSE_PASSWORD_RESET, 'email');
            } catch (\Throwable $e) {
                Log::warning(sprintf('Échec de l\'envoi du code de réinitialisation de mot de passe pour %s : %s', $user->email, $e->getMessage()));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Si un compte existe avec cette adresse, un code de réinitialisation a été envoyé.',
            'data' => [],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['code' => ['Ce code est invalide ou a expiré.']]);
        }

        $this->otp->verifyResetCode(
            $user->email,
            \App\Models\VerificationCode::PURPOSE_PASSWORD_RESET,
            'email',
            $validated['code']
        );

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();
        $user->tokens()->delete();

        $this->otp->consumeResetCodes($user->email, \App\Models\VerificationCode::PURPOSE_PASSWORD_RESET, 'email');
        $this->audit->record($user->id, 'password_reset');

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé. Vous pouvez vous connecter.',
            'data' => [],
        ]);
    }

    public function sendEmailVerification(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if ($user && ! $user->email_verified_at) {
            $this->otp->sendResetCode($user->email, VerificationCode::PURPOSE_EMAIL_VERIFY, 'email');
        }

        return response()->json([
            'success' => true,
            'message' => 'Si un compte non vérifié existe, un code de confirmation a été envoyé.',
            'data' => ['resend_after' => OtpService::RESEND_SECONDS],
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages(['code' => ['Ce code est invalide ou a expiré.']]);
        }

        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Adresse e-mail déjà vérifiée.',
                'data' => ['email_verified' => true],
            ]);
        }

        $this->otp->verifyResetCode($user->email, VerificationCode::PURPOSE_EMAIL_VERIFY, 'email', $validated['code']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->otp->consumeResetCodes($user->email, VerificationCode::PURPOSE_EMAIL_VERIFY, 'email');
        $this->audit->record($user->id, 'email_verified');

        return response()->json([
            'success' => true,
            'message' => 'Adresse e-mail vérifiée.',
            'data' => ['email_verified' => true],
        ]);
    }
}
