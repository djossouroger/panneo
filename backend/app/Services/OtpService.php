<?php

namespace App\Services;

use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public const LENGTH = 6;
    public const TTL_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;

    public const TTL_EMAIL_CHANGE_MINUTES = 10;
    public const TTL_PASSWORD_RESET_MINUTES = 10;

    public function __construct(private readonly ?SmsProviderInterface $sms = null)
    {
    }

    public function generate(): string
    {
        return (string) random_int(100000, 999999);
    }

    public function create(User $user, string $purpose, string $channel, string $recipient, ?int $ttlMinutes = null): string
    {
        $code = $this->generate();

        VerificationCode::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($ttlMinutes ?? self::TTL_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        return $code;
    }

    public function send(string $purpose, string $channel, string $recipient, string $code): void
    {
        $message = sprintf('Pannéo : votre code de vérification est %s. Valable %d minutes. Ne le partagez avec personne.', $code, $purpose === VerificationCode::PURPOSE_PASSWORD_RESET ? self::TTL_PASSWORD_RESET_MINUTES : ($purpose === VerificationCode::PURPOSE_EMAIL_CHANGE || $purpose === VerificationCode::PURPOSE_PHONE_CHANGE ? self::TTL_EMAIL_CHANGE_MINUTES : self::TTL_MINUTES));

        $delivery = config('otp.delivery', 'log');

        if ($delivery === 'log') {
            Log::info(sprintf('OTP Pannéo [%s] pour %s : %s', $purpose, $recipient, $code));

            return;
        }

        if ($channel === 'email') {
            Mail::raw($message, function ($mail) use ($purpose, $recipient) {
                $mail->to($recipient)->subject($this->mailSubject($purpose));
            });

            return;
        }

        if ($this->sms !== null) {
            $this->sms->send($recipient, $message);
        }
    }

    private function mailSubject(string $purpose): string
    {
        return match ($purpose) {
            VerificationCode::PURPOSE_PASSWORD_RESET => 'Pannéo — Réinitialisation de votre mot de passe',
            VerificationCode::PURPOSE_EMAIL_VERIFY => 'Pannéo — Confirmez votre adresse e-mail',
            VerificationCode::PURPOSE_EMAIL_CHANGE => 'Pannéo — Confirmation de votre nouvelle adresse e-mail',
            default => 'Pannéo — Code de vérification',
        };
    }

    public function verify(User $user, string $purpose, string $channel, string $recipient, string $code): void
    {
        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $record || $record->isExpired()) {
            throw ValidationException::withMessages(['code' => ['Ce code a expiré. Demandez un nouveau code.']]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => ['Trop de tentatives. Demandez un nouveau code.']]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw ValidationException::withMessages(['code' => ['Ce code est incorrect.']]);
        }

        $record->forceFill(['expires_at' => now()->subSecond()])->save();
    }

    public function canResend(?VerificationCode $latest): bool
    {
        return $latest === null || $latest->last_sent_at === null || $latest->last_sent_at->diffInSeconds(now()) >= self::RESEND_SECONDS;
    }

    public function sendResetCode(string $recipient, string $purpose, string $channel): void
    {
        $latest = VerificationCode::query()
            ->where('user_id', null)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->orderByDesc('id')
            ->first();

        if (! $this->canResend($latest)) {
            $remaining = self::RESEND_SECONDS - (int) $latest->last_sent_at->diffInSeconds(now());

            throw ValidationException::withMessages(['recipient' => [sprintf('Veuillez patienter %d secondes avant de redemander un code.', $remaining)]]);
        }

        $code = $this->generate();

        VerificationCode::create([
            'user_id' => null,
            'purpose' => $purpose,
            'channel' => $channel,
            'recipient' => $recipient,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_PASSWORD_RESET_MINUTES),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $this->send($purpose, $channel, $recipient, $code);
    }

    public function verifyResetCode(string $recipient, string $purpose, string $channel, string $code): void
    {
        $record = VerificationCode::query()
            ->where('user_id', null)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $record || $record->isExpired()) {
            throw ValidationException::withMessages(['code' => ['Ce code a expiré. Demandez un nouveau code.']]);
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => ['Trop de tentatives. Demandez un nouveau code.']]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw ValidationException::withMessages(['code' => ['Ce code est incorrect.']]);
        }
    }

    public function consumeResetCodes(string $recipient, string $purpose, string $channel): void
    {
        VerificationCode::query()
            ->where('user_id', null)
            ->where('purpose', $purpose)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->update(['expires_at' => now()->subSecond()]);
    }
}
