<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRepairRequestRequest;
use App\Http\Resources\AvailableArtisanResource;
use App\Http\Resources\RepairRequestOfferResource;
use App\Http\Resources\RepairRequestResource;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\User;
use App\Services\MatchingService;
use App\Services\NotificationService;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairRequestController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly MatchingService $matching,
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $statusFilter = $request->query('status');
        $categoryId = $request->query('category_id');
        $period = (int) $request->query('period', 0);

        $query = $request->user()
            ->repairRequests()
            ->with([
                'category',
                'acceptedArtisan.artisanProfile.category',
                'review.client',
                'activeOffer.artisan.artisanProfile.category',
                'latestOffer.artisan.artisanProfile.category',
            ]);

        if ($statusFilter === 'actives') {
            $query->whereIn('status', [RepairRequest::STATUS_PENDING, RepairRequest::STATUS_AWAITING_ARTISAN, RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS]);
        } elseif ($statusFilter === 'terminees') {
            $query->where('status', RepairRequest::STATUS_COMPLETED);
        } elseif ($statusFilter === 'annulees') {
            $query->where('status', RepairRequest::STATUS_CANCELLED);
        } elseif ($statusFilter === 'historique') {
            $query->whereIn('status', [RepairRequest::STATUS_COMPLETED, RepairRequest::STATUS_CANCELLED]);
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        if ($period === 7 || $period === 30) {
            $query->whereDate('created_at', '>=', now()->subDays($period)->startOfDay());
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('reference', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhereHas('acceptedArtisan', fn ($artisan) => $artisan->where('name', 'ilike', "%{$search}%"));
            });
        }

        $repairRequests = $query->latest()->paginate(ApiPagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Demandes récupérées.',
            'data' => RepairRequestResource::collection($repairRequests)->resolve(),
            'meta' => ApiPagination::meta($repairRequests),
        ]);
    }

    public function store(StoreRepairRequestRequest $request)
    {
        $repairRequest = DB::transaction(function () use ($request) {
            $repairRequest = RepairRequest::create([
                'client_id' => $request->user()->id,
                'category_id' => $request->integer('category_id'),
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'city' => $request->validated('city'),
                'district' => $request->validated('district'),
                'address_details' => $request->validated('address_details'),
                'images' => $this->storeImages($request),
                'status' => RepairRequest::STATUS_PENDING,
            ]);

            $repairRequest->assignReference();

            return $repairRequest->load('category');
        });

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée.',
            'data' => RepairRequestResource::make($repairRequest)->resolve(),
        ], 201);
    }

    private function storeImages(StoreRepairRequestRequest $request): ?array
    {
        if (! $request->hasFile('images')) {
            return null;
        }

        $paths = [];
        foreach ($request->file('images') as $image) {
            $paths[] = $image->store('request-images', 'public');
        }

        return $paths;
    }

    public function show(Request $request, RepairRequest $repairRequest)
    {
        $this->authorizeOwner($request, $repairRequest);

        return response()->json([
            'success' => true,
            'message' => 'Demande récupérée.',
            'data' => RepairRequestResource::make($this->loadForClient($repairRequest))->resolve(),
        ]);
    }

    public function availableArtisans(Request $request, RepairRequest $repairRequest)
    {
        $this->authorizeOwner($request, $repairRequest);

        if (! $repairRequest->isPending()) {
            return $this->unprocessable('status', 'Cette demande ne permet pas une recherche de dépanneur.');
        }

        $rejectedArtisanIds = $repairRequest->offers()
            ->where('status', RepairRequestOffer::STATUS_REJECTED)
            ->pluck('artisan_id')
            ->all();

        $artisans = $this->matching->candidateArtisans($repairRequest, $rejectedArtisanIds);

        return response()->json([
            'success' => true,
            'message' => 'Dépanneurs disponibles récupérés.',
            'data' => AvailableArtisanResource::collection($artisans)->resolve(),
        ]);
    }

    public function storeOffer(Request $request, RepairRequest $repairRequest)
    {
        $validated = $request->validate([
            'artisan_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $offer = DB::transaction(function () use ($request, $repairRequest, $validated) {
            $lockedRequest = RepairRequest::with('category')
                ->whereKey($repairRequest->id)
                ->where('client_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                abort(403, 'Vous ne pouvez envoyer que vos propres demandes.');
            }

            if ($lockedRequest->status !== RepairRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => [$this->requestCannotReceiveOfferMessage($lockedRequest->status)],
                ]);
            }

            if ($lockedRequest->offers()->where('status', RepairRequestOffer::STATUS_PENDING)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'status' => ['Une proposition est déjà en attente pour cette demande.'],
                ]);
            }

            $artisan = User::with([
                'artisanProfile',
                'categories',
                'serviceAreas',
                'workingHours',
                'unavailabilities',
            ])->whereKey($validated['artisan_id'])->lockForUpdate()->first();

            $this->matching->ensureCompatible($artisan, $lockedRequest);

            if ($lockedRequest->offers()
                ->where('artisan_id', $artisan->id)
                ->where('status', RepairRequestOffer::STATUS_REJECTED)
                ->exists()) {
                throw ValidationException::withMessages([
                    'artisan_id' => ['Cet artisan a déjà refusé cette demande.'],
                ]);
            }

            $offer = RepairRequestOffer::create([
                'repair_request_id' => $lockedRequest->id,
                'artisan_id' => $artisan->id,
                'status' => RepairRequestOffer::STATUS_PENDING,
            ]);

            $lockedRequest->forceFill([
                'status' => RepairRequest::STATUS_AWAITING_ARTISAN,
            ])->save();

            $this->notifications->send(
                $artisan,
                Notification::TYPE_REPAIR_REQUEST_RECEIVED,
                'Nouvelle demande de dépannage',
                sprintf('Une nouvelle demande de %s vous a été envoyée à %s, %s.', $lockedRequest->category?->name ?? 'dépannage', $lockedRequest->district, $lockedRequest->city),
                [
                    'repair_request_id' => $lockedRequest->id,
                    'offer_id' => $offer->id,
                    'reference' => $lockedRequest->reference,
                ]
            );

            return $offer->load([
                'artisan.artisanProfile.category',
                'repairRequest.category',
                'repairRequest.client',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Demande envoyée au dépanneur.',
            'data' => RepairRequestOfferResource::make($offer)->resolve(),
        ], 201);
    }

    public function cancel(Request $request, RepairRequest $repairRequest)
    {
        $updatedRequest = DB::transaction(function () use ($request, $repairRequest) {
            $lockedRequest = RepairRequest::whereKey($repairRequest->id)
                ->where('client_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedRequest) {
                abort(403, 'Vous ne pouvez annuler que vos propres demandes.');
            }

            if (! $lockedRequest->canBeCancelledByClient()) {
                throw ValidationException::withMessages([
                    'status' => ['La demande ne peut plus être annulée.'],
                ]);
            }

            if ($lockedRequest->isAwaitingArtisan()) {
                RepairRequestOffer::where('repair_request_id', $lockedRequest->id)
                    ->where('status', RepairRequestOffer::STATUS_PENDING)
                    ->lockForUpdate()
                    ->update(['status' => RepairRequestOffer::STATUS_CANCELLED]);
            }

            $lockedRequest->forceFill([
                'status' => RepairRequest::STATUS_CANCELLED,
            ])->save();

            return $lockedRequest->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Demande annulée.',
            'data' => RepairRequestResource::make($this->loadForClient($updatedRequest))->resolve(),
        ]);
    }

    private function authorizeOwner(Request $request, RepairRequest $repairRequest): void
    {
        if ($repairRequest->client_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez accéder qu’à vos propres demandes.');
        }
    }

    private function loadForClient(RepairRequest $repairRequest): RepairRequest
    {
        return $repairRequest->load([
            'category',
            'acceptedArtisan.artisanProfile.category',
            'review.client',
            'activeOffer.artisan.artisanProfile.category',
            'latestOffer.artisan.artisanProfile.category',
            'offers.artisan.artisanProfile.category',
        ]);
    }

    private function requestCannotReceiveOfferMessage(string $status): string
    {
        return match ($status) {
            RepairRequest::STATUS_AWAITING_ARTISAN => 'Une proposition est déjà en attente pour cette demande.',
            RepairRequest::STATUS_ACCEPTED => 'Cette demande a déjà été acceptée.',
            RepairRequest::STATUS_CANCELLED => 'Cette demande a été annulée.',
            default => 'Cette demande ne peut pas recevoir de proposition.',
        };
    }

    private function unprocessable(string $field, string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => [$field => [$message]],
        ], 422);
    }
}
