<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Notification;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index()
    {
        $submissions = ArtisanVerificationSubmission::with(['artisan.artisanProfile', 'documents'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('admin.verifications.index', compact('submissions'));
    }

    public function show(ArtisanVerificationSubmission $submission)
    {
        $submission->load(['artisan.artisanProfile.categories', 'artisan.artisanProfile.serviceAreas', 'documents']);

        return view('admin.verifications.show', compact('submission'));
    }

    public function approve(ArtisanVerificationSubmission $submission)
    {
        DB::transaction(function () use ($submission) {
            $profile = $submission->artisan->artisanProfile;

            $submission->forceFill([
                'status' => ArtisanVerificationSubmission::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => request()->user()->id,
                'rejection_reason' => null,
            ])->save();

            if ($profile) {
                $profile->forceFill([
                    'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
                    'verified_at' => now(),
                    'verified_by' => request()->user()->id,
                ])->save();
            }

            $this->notifications->send(
                $submission->artisan,
                Notification::TYPE_ARTISAN_ACCOUNT_VERIFIED,
                'Votre compte a été validé',
                'Votre profil professionnel Pannéo est maintenant actif.',
                ['verification_submission_id' => $submission->id]
            );
        });

        $this->audit->record(request()->user()->id, 'artisan_verification_approved', ['artisan_id' => $submission->artisan_id]);

        return back()->with('success', 'Demande approuvée. Le profil de l’artisan est vérifié.');
    }

    public function reject(Request $request, ArtisanVerificationSubmission $submission)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($submission, $validated) {
            $profile = $submission->artisan->artisanProfile;

            $submission->forceFill([
                'status' => ArtisanVerificationSubmission::STATUS_REJECTED,
                'reviewed_at' => now(),
                'reviewed_by' => request()->user()->id,
                'rejection_reason' => $validated['reason'],
            ])->save();

            if ($profile) {
                $profile->forceFill([
                    'verification_status' => ArtisanProfile::VERIFICATION_REJECTED,
                ])->save();
            }

            $this->notifications->send(
                $submission->artisan,
                Notification::TYPE_VERIFICATION_REJECTED,
                'Vérification refusée',
                'Votre demande de vérification a été refusée. Motif : '.$validated['reason'],
                ['verification_submission_id' => $submission->id]
            );
        });

        $this->audit->record(request()->user()->id, 'artisan_verification_rejected', ['artisan_id' => $submission->artisan_id]);

        return back()->with('success', 'Demande refusée. L’artisan a été notifié.');
    }

    public function reopen(ArtisanVerificationSubmission $submission)
    {
        if ($submission->status !== ArtisanVerificationSubmission::STATUS_REJECTED) {
            throw ValidationException::withMessages(['submission' => ['Seule une demande refusée peut être rouverte.']]);
        }

        $submission->artisan->artisanProfile()?->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

        return back()->with('success', 'Le profil a été repassé en attente de vérification.');
    }

    public function download(\App\Models\ArtisanVerificationDocument $document)
    {
        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function image(\App\Models\ArtisanVerificationDocument $document)
    {
        if (! str_starts_with((string) $document->mime_type, 'image/')) {
            abort(404, 'Ce document n’est pas une image.');
        }

        return Storage::disk('local')->response($document->file_path);
    }
}
