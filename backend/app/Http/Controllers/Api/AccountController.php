<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AccountController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->forceFill(['name' => $validated['name']])->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour.',
            'data' => ['user' => $request->user()->fresh()->load('artisanProfile.categories')->toArray()],
        ]);
    }

    public function uploadProfilePhoto(Request $request)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $validated['photo']->store('profile-photos', 'public');

        $user->forceFill(['profile_photo_path' => $path])->save();

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil mise à jour.',
            'data' => ['profile_photo_url' => url('storage/'.$path)],
        ]);
    }

    public function requestEmailChange(Request $request)
    {
        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        $user = $request->user();

        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationCode::PURPOSE_EMAIL_CHANGE)
            ->where('channel', 'email')
            ->where('recipient', $validated['new_email'])
            ->orderByDesc('id')
            ->first();

        if (! $this->otp->canResend($latest)) {
            $remaining = OtpService::RESEND_SECONDS - (int) $latest->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages(['new_email' => [sprintf('Veuillez patienter %d secondes avant de redemander un code.', $remaining)]]);
        }

        $code = $this->otp->create($user, VerificationCode::PURPOSE_EMAIL_CHANGE, 'email', $validated['new_email'], OtpService::TTL_EMAIL_CHANGE_MINUTES);
        $this->otp->send(VerificationCode::PURPOSE_EMAIL_CHANGE, 'email', $validated['new_email'], $code);

        return response()->json([
            'success' => true,
            'message' => 'Un code de confirmation a été envoyé à la nouvelle adresse.',
            'data' => [],
        ]);
    }

    public function changeEmail(Request $request)
    {
        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        $this->otp->verify(
            $user,
            VerificationCode::PURPOSE_EMAIL_CHANGE,
            'email',
            $validated['new_email'],
            $validated['code']
        );

        $oldEmail = $user->email;
        $user->forceFill(['email' => $validated['new_email'], 'email_verified_at' => now()])->save();

        $this->audit->record($user->id, 'email_changed', ['from' => $oldEmail, 'to' => $validated['new_email']]);

        return response()->json([
            'success' => true,
            'message' => 'Adresse e-mail mise à jour.',
            'data' => ['user' => $user->fresh()->load('artisanProfile.categories')->toArray()],
        ]);
    }

    public function requestPhoneChange(Request $request)
    {
        $validated = $request->validate([
            'new_phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
        ]);

        $user = $request->user();

        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationCode::PURPOSE_PHONE_CHANGE)
            ->where('channel', 'sms')
            ->where('recipient', $validated['new_phone'])
            ->orderByDesc('id')
            ->first();

        if (! $this->otp->canResend($latest)) {
            $remaining = OtpService::RESEND_SECONDS - (int) $latest->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages(['new_phone' => [sprintf('Veuillez patienter %d secondes avant de redemander un code.', $remaining)]]);
        }

        $code = $this->otp->create($user, VerificationCode::PURPOSE_PHONE_CHANGE, 'sms', $validated['new_phone'], OtpService::TTL_EMAIL_CHANGE_MINUTES);
        $this->otp->send(VerificationCode::PURPOSE_PHONE_CHANGE, 'sms', $validated['new_phone'], $code);

        return response()->json([
            'success' => true,
            'message' => 'Un code de confirmation a été envoyé au nouveau numéro.',
            'data' => [],
        ]);
    }

    public function changePhone(Request $request)
    {
        $validated = $request->validate([
            'new_phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        $this->otp->verify(
            $user,
            VerificationCode::PURPOSE_PHONE_CHANGE,
            'sms',
            $validated['new_phone'],
            $validated['code']
        );

        $oldPhone = $user->phone;
        $user->forceFill(['phone' => $validated['new_phone'], 'phone_verified_at' => now()])->save();

        $this->audit->record($user->id, 'phone_changed', ['from' => $oldPhone, 'to' => $validated['new_phone']]);

        return response()->json([
            'success' => true,
            'message' => 'Numéro de téléphone mis à jour.',
            'data' => ['user' => $user->fresh()->load('artisanProfile.categories')->toArray()],
        ]);
    }

    public function sessions(Request $request)
    {
        $sessions = $request->user()
            ->sessions()
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'last_used_at', 'created_at'])
            ->map(function (PersonalAccessToken $token) use ($request) {
                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toISOString(),
                    'created_at' => $token->created_at?->toISOString(),
                    'is_current' => $token->id === $request->user()->currentAccessToken()?->id,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Sessions récupérées.',
            'data' => ['sessions' => $sessions],
        ]);
    }

    public function revokeSession(Request $request, string $session)
    {
        $token = PersonalAccessToken::where('tokenable_id', $request->user()->id)
            ->where('tokenable_type', \App\Models\User::class)
            ->findOrFail($session);

        if ($token->id === $request->user()->currentAccessToken()?->id) {
            throw ValidationException::withMessages(['session' => ['Impossible de révoquer la session courante.']]);
        }

        $token->delete();

        $this->audit->record($request->user()->id, 'session_revoked', ['session_id' => $token->id]);

        return response()->json([
            'success' => true,
            'message' => 'Session révoquée.',
            'data' => [],
        ]);
    }

    public function revokeOtherSessions(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Mot de passe incorrect.']]);
        }

        $currentId = $request->user()->currentAccessToken()?->id;

        $user->tokens()
            ->where('id', '!=', $currentId)
            ->delete();

        $this->audit->record($user->id, 'session_revoked', ['scope' => 'others']);

        return response()->json([
            'success' => true,
            'message' => 'Autres sessions déconnectées.',
            'data' => [],
        ]);
    }

    public function deleteAccount(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Mot de passe incorrect.']]);
        }

        $blockedStatuses = [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS];

        $hasActiveIntervention = RepairRequest::where('client_id', $user->id)
            ->whereIn('status', $blockedStatuses)
            ->exists();

        if (! $hasActiveIntervention) {
            $hasActiveIntervention = RepairRequest::where('accepted_artisan_id', $user->id)
                ->whereIn('status', $blockedStatuses)
                ->exists();
        }

        if ($hasActiveIntervention) {
            throw ValidationException::withMessages(['account' => ['Impossible de supprimer le compte avec une intervention en cours.']]);
        }

        $user->tokens()->delete();
        $user->delete();

        $this->audit->record($user->id, 'account_deleted');

        return response()->json([
            'success' => true,
            'message' => 'Compte supprimé.',
            'data' => [],
        ]);
    }
}
