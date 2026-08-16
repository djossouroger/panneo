<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhoneAuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {
    }

    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();

        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationCode::PURPOSE_PHONE_VERIFY)
            ->where('channel', 'sms')
            ->where('recipient', $validated['phone'])
            ->orderByDesc('id')
            ->first();

        if (! $this->otp->canResend($latest)) {
            $remaining = OtpService::RESEND_SECONDS - (int) $latest->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages(['phone' => [sprintf('Veuillez patienter %d secondes avant de redemander un code.', $remaining)]]);
        }

        $code = $this->otp->create($user, VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', $validated['phone']);
        $this->otp->send(VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', $validated['phone'], $code);

        return response()->json([
            'success' => true,
            'message' => 'Code de vérification envoyé par SMS.',
            'data' => ['resend_after' => OtpService::RESEND_SECONDS],
        ]);
    }

    public function resend(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $user = $request->user();

        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', VerificationCode::PURPOSE_PHONE_VERIFY)
            ->where('channel', 'sms')
            ->where('recipient', $validated['phone'])
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            throw ValidationException::withMessages(['phone' => ['Aucun code n’a été demandé pour ce numéro.']]);
        }

        if (! $this->otp->canResend($latest)) {
            $remaining = OtpService::RESEND_SECONDS - (int) $latest->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages(['phone' => [sprintf('Veuillez patienter %d secondes avant de redemander un code.', $remaining)]]);
        }

        $code = $this->otp->create($user, VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', $validated['phone']);
        $this->otp->send(VerificationCode::PURPOSE_PHONE_VERIFY, 'sms', $validated['phone'], $code);

        return response()->json([
            'success' => true,
            'message' => 'Nouveau code envoyé.',
            'data' => ['resend_after' => OtpService::RESEND_SECONDS],
        ]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        $this->otp->verify(
            $user,
            VerificationCode::PURPOSE_PHONE_VERIFY,
            'sms',
            $validated['phone'],
            $validated['code']
        );

        $user->forceFill(['phone' => $validated['phone'], 'phone_verified_at' => now()])->save();

        $this->audit->record($user->id, 'phone_verified', ['phone' => $validated['phone']]);

        return response()->json([
            'success' => true,
            'message' => 'Numéro de téléphone vérifié.',
            'data' => ['phone_verified' => true],
        ]);
    }
}
