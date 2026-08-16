<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArtisanProfileResource;
use App\Http\Resources\PublicArtisanProfileResource;
use App\Http\Resources\RepairRequestResource;
use App\Models\ArtisanProfile;
use App\Models\ArtisanUnavailability;
use App\Models\ArtisanVerificationDocument;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\MatchingService;
use App\Services\NotificationService;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ArtisanController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MatchingService $matching,
    ) {
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load([
            'artisanProfile.categories',
            'artisanProfile.serviceAreas',
            'artisanProfile.workingHours',
            'artisanProfile.unavailabilities',
            'artisanProfile.portfolioItems',
            'artisanProfile.latestVerificationSubmission.documents',
        ]);

        if (! $user->artisanProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil artisan introuvable.',
                'errors' => ['profile' => ['Aucun profil artisan.']],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil artisan récupéré.',
            'data' => ArtisanProfileResource::make($user)->resolve(),
        ]);
    }

    public function publicProfile(User $artisan)
    {
        if ($artisan->role !== 'artisan' || ! $artisan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Profil indisponible.',
                'errors' => ['artisan' => ['Ce profil n’est plus disponible.']],
            ], 404);
        }

        $artisan->load([
            'artisanProfile.categories',
            'artisanProfile.serviceAreas',
            'artisanProfile.portfolioItems',
        ])->loadCount([
            'reviewsReceived as reviews_count',
            'acceptedRepairRequests as completed_interventions' => fn ($query) => $query->where('status', RepairRequest::STATUS_COMPLETED),
        ])->loadAvg('reviewsReceived as average_rating', 'rating');

        if (! $artisan->artisanProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil indisponible.',
                'errors' => ['artisan' => ['Ce profil n’est plus disponible.']],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil du dépanneur récupéré.',
            'data' => PublicArtisanProfileResource::make($artisan)->resolve(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'years_of_experience' => ['nullable', 'integer', 'between:0,60'],
            'specialties' => ['nullable', 'array', 'max:5'],
            'specialties.*' => ['required', 'string', 'max:80'],
        ]);

        $profile = $request->user()->artisanProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'description' => $validated['description'] ?? null,
                'years_of_experience' => $validated['years_of_experience'] ?? null,
                'specialties' => $validated['specialties'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil artisan mis à jour.',
            'data' => ArtisanProfileResource::make($request->user()->fresh([
                'artisanProfile.categories',
                'artisanProfile.serviceAreas',
                'artisanProfile.workingHours',
                'artisanProfile.unavailabilities',
                'artisanProfile.portfolioItems',
                'artisanProfile.latestVerificationSubmission.documents',
            ]))->resolve(),
        ]);
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $profile = DB::transaction(function () use ($user, $validated) {
            $profile = ArtisanProfile::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                return null;
            }

            if ($validated['is_available']) {
                $hasActiveRequest = RepairRequest::where('accepted_artisan_id', $user->id)
                    ->whereIn('status', [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS])
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveRequest) {
                    throw ValidationException::withMessages([
                        'is_available' => ['Terminez votre intervention actuelle avant de vous rendre disponible.'],
                    ]);
                }
            }

            $profile->forceFill(['is_available' => $validated['is_available']])->save();

            return $profile;
        });

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil artisan introuvable.',
                'errors' => ['profile' => ['Aucun profil artisan.']],
            ], 404);
        }

        $user->setRelation('artisanProfile', $profile->fresh());
        $withinHours = $this->matching->isWithinWorkingHours($user);

        return response()->json([
            'success' => true,
            'message' => 'Disponibilité mise à jour.',
            'data' => [
                'is_available' => (bool) $profile->is_available,
                'within_working_hours' => $withinHours,
                'hors_horaires' => (bool) $profile->is_available && ! $withinHours,
            ],
        ]);
    }

    public function uploadProfilePhoto(Request $request)
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $user = $request->user();
        $profile = $user->artisanProfile;

        if (! $profile) {
            throw ValidationException::withMessages(['profile' => ['Profil artisan introuvable.']]);
        }

        if ($profile->profile_photo_path) {
            Storage::disk('public')->delete($profile->profile_photo_path);
        }

        $path = $validated['photo']->store('profile-photos', 'public');

        $profile->forceFill(['profile_photo_path' => $path])->save();

        return response()->json([
            'success' => true,
            'message' => 'Photo de profil mise à jour.',
            'data' => ['profile_photo_url' => url('storage/'.$path)],
        ]);
    }

    public function updateCategories(Request $request)
    {
        $validated = $request->validate([
            'category_ids' => ['required', 'array', 'min:1', 'max:3'],
            'category_ids.*' => ['required', 'integer', 'exists:categories,id'],
            'primary_category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        if (! in_array($validated['primary_category_id'], $validated['category_ids'], true)) {
            throw ValidationException::withMessages(['primary_category_id' => ['Le métier principal doit faire partie des métiers sélectionnés.']]);
        }

        $user = $request->user();

        $sync = [];
        foreach ($validated['category_ids'] as $categoryId) {
            $sync[$categoryId] = ['is_primary' => (int) $categoryId === (int) $validated['primary_category_id']];
        }

        $user->categories()->sync($sync);

        $profile = $user->artisanProfile()->updateOrCreate(['user_id' => $user->id], []);
        if ($profile->category_id && ! in_array((int) $profile->category_id, array_map('intval', $validated['category_ids']), true)) {
            $profile->forceFill(['category_id' => $validated['primary_category_id']])->save();
        } elseif ($profile->category_id === null) {
            $profile->forceFill(['category_id' => $validated['primary_category_id']])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Métiers mis à jour.',
            'data' => ArtisanProfileResource::make($user->fresh([
                'artisanProfile.categories',
                'artisanProfile.serviceAreas',
                'artisanProfile.workingHours',
                'artisanProfile.unavailabilities',
                'artisanProfile.portfolioItems',
                'artisanProfile.latestVerificationSubmission.documents',
            ]))->resolve(),
        ]);
    }

    public function updateServiceAreas(Request $request)
    {
        $validated = $request->validate([
            'areas' => ['required', 'array', 'min:1', 'max:10'],
            'areas.*.city' => ['required', 'string', 'max:255'],
            'areas.*.district' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $user->serviceAreas()->delete();

        foreach ($validated['areas'] as $area) {
            $city = trim($area['city']);
            $district = isset($area['district']) && trim((string) $area['district']) !== '' ? trim($area['district']) : null;

            $user->serviceAreas()->create([
                'city' => $city,
                'district' => $district,
            ]);
        }

        $first = $user->serviceAreas()->first();
        if ($first) {
            $user->artisanProfile()?->update(['city' => $first->city, 'district' => $first->district]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Zones d’intervention mises à jour.',
            'data' => ArtisanProfileResource::make($user->fresh([
                'artisanProfile.categories',
                'artisanProfile.serviceAreas',
                'artisanProfile.workingHours',
                'artisanProfile.unavailabilities',
                'artisanProfile.portfolioItems',
                'artisanProfile.latestVerificationSubmission.documents',
            ]))->resolve(),
        ]);
    }

    public function updateWorkingHours(Request $request)
    {
        $validated = $request->validate([
            'hours' => ['required', 'array', 'min:1', 'max:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.start_time' => ['nullable', 'date_format:H:i'],
            'hours.*.end_time' => ['nullable', 'date_format:H:i', 'after:hours.*.start_time'],
            'hours.*.is_working_day' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $seenDays = [];
        foreach ($validated['hours'] as $hour) {
            if (in_array((int) $hour['day_of_week'], $seenDays, true)) {
                throw ValidationException::withMessages(['hours' => ['Une seule plage horaire par jour est autorisée.']]);
            }
            $seenDays[] = (int) $hour['day_of_week'];
        }

        $user->workingHours()->delete();

        foreach ($validated['hours'] as $hour) {
            $user->workingHours()->create([
                'day_of_week' => (int) $hour['day_of_week'],
                'start_time' => $hour['start_time'] ?? null,
                'end_time' => $hour['end_time'] ?? null,
                'is_working_day' => (bool) $hour['is_working_day'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Horaires de travail mis à jour.',
            'data' => ArtisanProfileResource::make($user->fresh([
                'artisanProfile.categories',
                'artisanProfile.serviceAreas',
                'artisanProfile.workingHours',
                'artisanProfile.unavailabilities',
                'artisanProfile.portfolioItems',
                'artisanProfile.latestVerificationSubmission.documents',
            ]))->resolve(),
        ]);
    }

    public function unavailabilities(Request $request)
    {
        $user = $request->user()->load('unavailabilities');

        return response()->json([
            'success' => true,
            'message' => 'Indisponibilités récupérées.',
            'data' => $user->unavailabilities->map(fn (ArtisanUnavailability $unavailability) => [
                'id' => $unavailability->id,
                'type' => $unavailability->type,
                'starts_at' => $unavailability->starts_at?->toISOString(),
                'ends_at' => $unavailability->ends_at?->toISOString(),
                'reason' => $unavailability->reason,
                'is_active' => $unavailability->isActiveAt(now()),
            ])->values(),
        ]);
    }

    public function storeUnavailability(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([ArtisanUnavailability::TYPE_PAUSE, ArtisanUnavailability::TYPE_LEAVE, ArtisanUnavailability::TYPE_TEMPORARY_UNAVAILABLE])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $unavailability = $user->unavailabilities()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Indisponibilité enregistrée.',
            'data' => [
                'id' => $unavailability->id,
                'type' => $unavailability->type,
                'starts_at' => $unavailability->starts_at?->toISOString(),
                'ends_at' => $unavailability->ends_at?->toISOString(),
                'reason' => $unavailability->reason,
                'is_active' => $unavailability->isActiveAt(now()),
            ],
        ], 201);
    }

    public function cancelUnavailability(Request $request, ArtisanUnavailability $unavailability)
    {
        if ($unavailability->artisan_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez modifier que vos propres indisponibilités.');
        }

        $unavailability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Indisponibilité supprimée.',
            'data' => [],
        ]);
    }

    public function portfolio(Request $request)
    {
        $user = $request->user()->load('portfolioItems');

        return response()->json([
            'success' => true,
            'message' => 'Portfolio récupéré.',
            'data' => $user->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'image_url' => url('storage/'.$item->image_path),
                'caption' => $item->caption,
            ])->values(),
        ]);
    }

    public function storePortfolioItem(Request $request)
    {
        $user = $request->user();

        if ($user->portfolioItems()->count() >= 6) {
            throw ValidationException::withMessages(['image' => ['Le portfolio est limité à 6 réalisations.']]);
        }

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'caption' => ['nullable', 'string', 'max:120'],
        ]);

        $path = $validated['image']->store('portfolio', 'public');

        $item = $user->portfolioItems()->create([
            'image_path' => $path,
            'caption' => $validated['caption'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Réalisation ajoutée au portfolio.',
            'data' => [
                'id' => $item->id,
                'image_url' => url('storage/'.$path),
                'caption' => $item->caption,
            ],
        ], 201);
    }

    public function deletePortfolioItem(Request $request, $item)
    {
        $portfolioItem = $request->user()->portfolioItems()->findOrFail($item);

        Storage::disk('public')->delete($portfolioItem->image_path);
        $portfolioItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Réalisation supprimée.',
            'data' => [],
        ]);
    }

    public function verification(Request $request)
    {
        $user = $request->user()->load([
            'artisanProfile.latestVerificationSubmission.documents',
        ]);

        $profile = $user->artisanProfile;

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profil artisan introuvable.',
                'errors' => ['profile' => ['Aucun profil artisan.']],
            ], 404);
        }

        $submission = $profile->latestVerificationSubmission;

        return response()->json([
            'success' => true,
            'message' => 'Statut de vérification récupéré.',
            'data' => [
                'verification_status' => $profile->verification_status,
                'has_pending_submission' => $submission?->isPending() ?? false,
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toISOString(),
                    'reviewed_at' => $submission->reviewed_at?->toISOString(),
                    'rejection_reason' => $submission->rejection_reason,
                    'documents' => $submission->documents->map(fn ($document) => [
                        'id' => $document->id,
                        'document_type' => $document->document_type,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'file_size' => $document->file_size,
                    ])->values(),
                ] : null,
            ],
        ]);
    }

    public function submitVerification(Request $request)
    {
        $user = $request->user();

        if ($user->artisanProfile?->isVerified()) {
            throw ValidationException::withMessages(['verification' => ['Votre profil est déjà vérifié.']]);
        }

        $hasPending = ArtisanVerificationSubmission::where('artisan_id', $user->id)
            ->where('status', ArtisanVerificationSubmission::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages(['verification' => ['Une demande de vérification est déjà en cours.']]);
        }

        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:2', 'max:4'],
            'documents.*.document_type' => ['required', Rule::in(['identity_document', 'professional_proof', 'selfie'])],
            'documents.*.file' => ['required', 'file'],
        ]);

        $documents = $validated['documents'];

        foreach ($documents as $index => $document) {
            $type = $document['document_type'];
            $file = $document['file'];
            $allowed = $type === 'professional_proof' ? ['jpg', 'jpeg', 'png', 'pdf'] : ['jpg', 'jpeg', 'png', 'webp'];

            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, $allowed, true)) {
                throw ValidationException::withMessages(["documents.$index.file" => [sprintf('Format non autorisé pour ce document (acceptés : %s).', implode(', ', $allowed))]]);
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                throw ValidationException::withMessages(["documents.$index.file" => ['Le fichier ne doit pas dépasser 5 Mo.']]);
            }

            if ($type !== 'professional_proof' && ! str_starts_with((string) $file->getMimeType(), 'image/')) {
                throw ValidationException::withMessages(["documents.$index.file" => ['Le fichier doit être une image valide (JPG, PNG, WEBP).']]);
            }
        }

        $types = collect($documents)->pluck('document_type')->all();

        if (! in_array('identity_document', $types, true) || ! in_array('selfie', $types, true)) {
            throw ValidationException::withMessages(['documents' => ['Fournissez une pièce d’identité et votre selfie avec la pièce.']]);
        }

        $submission = DB::transaction(function () use ($user, $documents) {
            $submission = ArtisanVerificationSubmission::create([
                'artisan_id' => $user->id,
                'status' => ArtisanVerificationSubmission::STATUS_PENDING,
                'submitted_at' => now(),
            ]);

            foreach ($documents as $document) {
                $path = $document['file']->store('verification-documents', 'local');

                ArtisanVerificationDocument::create([
                    'submission_id' => $submission->id,
                    'document_type' => $document['document_type'],
                    'file_path' => $path,
                    'original_name' => $document['file']->getClientOriginalName(),
                    'mime_type' => $document['file']->getMimeType(),
                    'file_size' => $document['file']->getSize(),
                ]);
            }

            $user->artisanProfile()?->update(['verification_status' => ArtisanProfile::VERIFICATION_PENDING]);

            return $submission;
        });

        return response()->json([
            'success' => true,
            'message' => 'Demande de vérification envoyée. Vous serez notifié de la décision.',
            'data' => [
                'submission_id' => $submission->id,
                'status' => $submission->status,
            ],
        ], 201);
    }

    public function cancelVerificationSubmission(Request $request)
    {
        $user = $request->user();

        $submission = ArtisanVerificationSubmission::where('artisan_id', $user->id)
            ->where('status', ArtisanVerificationSubmission::STATUS_PENDING)
            ->first();

        if (! $submission) {
            throw ValidationException::withMessages(['verification' => ['Aucune demande de vérification en cours.']]);
        }

        $wasRejectedBefore = ArtisanVerificationSubmission::where('artisan_id', $user->id)
            ->where('status', ArtisanVerificationSubmission::STATUS_REJECTED)
            ->exists();

        $submission->documents()->delete();
        $submission->delete();

        $user->artisanProfile()?->update([
            'verification_status' => $wasRejectedBefore ? ArtisanProfile::VERIFICATION_REJECTED : ArtisanProfile::VERIFICATION_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande de vérification annulée.',
            'data' => [],
        ]);
    }

    public function downloadVerificationDocument(Request $request, ArtisanVerificationDocument $document)
    {
        $submission = $document->submission;

        if ($submission->artisan_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez accéder qu’à vos propres documents.');
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function repairRequests(Request $request)
    {
        $status = $request->query('status');

        $query = RepairRequest::where('accepted_artisan_id', $request->user()->id)
            ->with([
                'category',
                'acceptedArtisan.artisanProfile.category',
                'review.client',
                'client',
            ]);

        if ($status === 'active') {
            $query->whereIn('status', [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS]);
        } elseif ($status === 'completed') {
            $query->where('status', RepairRequest::STATUS_COMPLETED);
        }

        $requests = $query->latest()->paginate(ApiPagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Interventions récupérées.',
            'data' => RepairRequestResource::collection($requests)->resolve(),
            'meta' => ApiPagination::meta($requests),
        ]);
    }

    public function showRepairRequest(Request $request, RepairRequest $repairRequest)
    {
        if ($repairRequest->accepted_artisan_id !== $request->user()->id) {
            abort(403, 'Vous n’êtes pas affecté à cette demande d’intervention.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Intervention récupérée.',
            'data' => RepairRequestResource::make($this->loadIntervention($repairRequest))->resolve(),
        ]);
    }

    public function startRepairRequest(Request $request, RepairRequest $repairRequest)
    {
        $startedRequest = DB::transaction(function () use ($request, $repairRequest) {
            $lockedRequest = RepairRequest::with('client')
                ->whereKey($repairRequest->id)
                ->where('accepted_artisan_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                abort(403, 'Vous n’êtes pas affecté à cette demande d’intervention.');
            }

            if ($lockedRequest->status === RepairRequest::STATUS_IN_PROGRESS) {
                return $lockedRequest;
            }

            if ($lockedRequest->status !== RepairRequest::STATUS_ACCEPTED) {
                throw ValidationException::withMessages([
                    'status' => ['Cette intervention ne peut pas être commencée.'],
                ]);
            }

            $startedAt = now();

            if ($lockedRequest->accepted_at) {
                $minimumStartedAt = $lockedRequest->accepted_at->copy()->addSecond();

                if ($startedAt->lessThan($minimumStartedAt)) {
                    $startedAt = $minimumStartedAt;
                }
            }

            $lockedRequest->forceFill([
                'status' => RepairRequest::STATUS_IN_PROGRESS,
                'started_at' => $lockedRequest->started_at ?? $startedAt,
            ])->save();

            if ($lockedRequest->wasChanged('status')) {
                $this->notifications->send(
                    $lockedRequest->client,
                    Notification::TYPE_REPAIR_REQUEST_STARTED,
                    'Intervention commencée',
                    'Votre dépanneur a commencé la prise en charge de votre panne.',
                    [
                        'repair_request_id' => $lockedRequest->id,
                        'reference' => $lockedRequest->reference,
                    ]
                );
            }

            return $lockedRequest;
        });

        return response()->json([
            'success' => true,
            'message' => $startedRequest->wasChanged('status') ? 'Intervention commencée.' : 'L’intervention est déjà en cours.',
            'data' => RepairRequestResource::make($this->loadIntervention($startedRequest->fresh()))->resolve(),
        ]);
    }

    public function completeRepairRequest(Request $request, RepairRequest $repairRequest)
    {
        $completedRequest = DB::transaction(function () use ($repairRequest, $request) {
            $lockedRequest = RepairRequest::with('client')
                ->whereKey($repairRequest->id)
                ->where('accepted_artisan_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                abort(403, 'Vous n’êtes pas affecté à cette demande d’intervention.');
            }

            if ($lockedRequest->status === RepairRequest::STATUS_COMPLETED) {
                return $lockedRequest;
            }

            if ($lockedRequest->status !== RepairRequest::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'status' => ['Cette intervention ne peut pas être terminée.'],
                ]);
            }

            $profile = ArtisanProfile::where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                throw ValidationException::withMessages([
                    'profile' => ['Profil artisan introuvable.'],
                ]);
            }

            $completedAt = now();

            if ($lockedRequest->started_at) {
                $minimumCompletedAt = $lockedRequest->started_at->copy()->addSecond();

                if ($completedAt->lessThan($minimumCompletedAt)) {
                    $completedAt = $minimumCompletedAt;
                }
            }

            $lockedRequest->forceFill([
                'status' => RepairRequest::STATUS_COMPLETED,
                'completed_at' => $lockedRequest->completed_at ?? $completedAt,
            ])->save();

            $profile->forceFill(['is_available' => true])->save();

            if ($lockedRequest->wasChanged('status')) {
                $this->notifications->send(
                    $lockedRequest->client,
                    Notification::TYPE_REPAIR_REQUEST_COMPLETED,
                    'Dépannage terminé',
                    'Votre intervention a été marquée comme terminée.',
                    [
                        'repair_request_id' => $lockedRequest->id,
                        'reference' => $lockedRequest->reference,
                    ]
                );
            }

            return $lockedRequest;
        });

        return response()->json([
            'success' => true,
            'message' => $completedRequest->wasChanged('status') ? 'Intervention terminée.' : 'L’intervention est déjà terminée.',
            'data' => RepairRequestResource::make($this->loadIntervention($completedRequest->fresh()))->resolve(),
        ]);
    }

    private function loadIntervention(RepairRequest $repairRequest): RepairRequest
    {
        return $repairRequest->load([
            'category',
            'acceptedArtisan.artisanProfile.category',
            'review.client',
            'client',
        ]);
    }
}
