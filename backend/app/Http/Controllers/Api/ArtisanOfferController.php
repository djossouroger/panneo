<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RepairRequestOfferResource;
use App\Models\ArtisanProfile;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArtisanOfferController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request)
    {
        $offers = $request->user()
            ->repairRequestOffers()
            ->with([
                'artisan.artisanProfile.category',
                'repairRequest.category',
                'repairRequest.client',
            ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(ApiPagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Offres reçues récupérées.',
            'data' => RepairRequestOfferResource::collection($offers)->resolve(),
            'meta' => ApiPagination::meta($offers),
        ]);
    }

    public function show(Request $request, RepairRequestOffer $offer)
    {
        $this->authorizeArtisanOffer($request, $offer);

        return response()->json([
            'success' => true,
            'message' => 'Offre récupérée.',
            'data' => RepairRequestOfferResource::make($this->loadOffer($offer))->resolve(),
        ]);
    }

    public function accept(Request $request, RepairRequestOffer $offer)
    {
        $acceptedOffer = DB::transaction(function () use ($request, $offer) {
            $lockedOffer = RepairRequestOffer::whereKey($offer->id)
                ->where('artisan_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOffer) {
                abort(403, 'Vous ne pouvez répondre qu’à vos propres offres.');
            }

            $this->ensureOfferCanBeAnswered($lockedOffer);

            $repairRequest = RepairRequest::with('client')
                ->whereKey($lockedOffer->repair_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($repairRequest->status !== RepairRequest::STATUS_AWAITING_ARTISAN) {
                throw ValidationException::withMessages([
                    'status' => ['Cette demande n’est plus disponible.'],
                ]);
            }

            $lockedOffer->forceFill([
                'status' => RepairRequestOffer::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            $repairRequest->forceFill([
                'status' => RepairRequest::STATUS_ACCEPTED,
                'accepted_artisan_id' => $request->user()->id,
                'accepted_at' => now(),
            ])->save();

            ArtisanProfile::where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->update(['is_available' => false]);

            $this->notifications->send(
                $repairRequest->client,
                Notification::TYPE_REPAIR_REQUEST_ACCEPTED,
                'Votre demande a été acceptée',
                sprintf('%s a accepté votre demande de dépannage.', $request->user()->name),
                [
                    'repair_request_id' => $repairRequest->id,
                    'reference' => $repairRequest->reference,
                ]
            );

            return $lockedOffer->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Intervention acceptée.',
            'data' => RepairRequestOfferResource::make($this->loadOffer($acceptedOffer))->resolve(),
        ]);
    }

    public function reject(Request $request, RepairRequestOffer $offer)
    {
        $rejectedOffer = DB::transaction(function () use ($request, $offer) {
            $lockedOffer = RepairRequestOffer::whereKey($offer->id)
                ->where('artisan_id', $request->user()->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOffer) {
                abort(403, 'Vous ne pouvez répondre qu’à vos propres offres.');
            }

            $this->ensureOfferCanBeAnswered($lockedOffer);

            $repairRequest = RepairRequest::with('client')
                ->whereKey($lockedOffer->repair_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($repairRequest->status !== RepairRequest::STATUS_AWAITING_ARTISAN) {
                throw ValidationException::withMessages([
                    'status' => ['Cette demande n’est plus disponible.'],
                ]);
            }

            $lockedOffer->forceFill([
                'status' => RepairRequestOffer::STATUS_REJECTED,
                'responded_at' => now(),
            ])->save();

            $repairRequest->forceFill([
                'status' => RepairRequest::STATUS_PENDING,
                'accepted_artisan_id' => null,
                'accepted_at' => null,
            ])->save();

            $this->notifications->send(
                $repairRequest->client,
                Notification::TYPE_REPAIR_REQUEST_REJECTED,
                'Le dépanneur n’est pas disponible',
                'Cet artisan ne peut pas prendre en charge votre demande. Vous pouvez en choisir un autre.',
                [
                    'repair_request_id' => $repairRequest->id,
                    'reference' => $repairRequest->reference,
                ]
            );

            return $lockedOffer->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Demande refusée.',
            'data' => RepairRequestOfferResource::make($this->loadOffer($rejectedOffer))->resolve(),
        ]);
    }

    private function authorizeArtisanOffer(Request $request, RepairRequestOffer $offer): void
    {
        if ($offer->artisan_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez accéder qu’à vos propres offres.');
        }
    }

    private function ensureOfferCanBeAnswered(RepairRequestOffer $offer): void
    {
        if ($offer->status !== RepairRequestOffer::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Cette offre a déjà reçu une réponse ou n’est plus disponible.'],
            ]);
        }
    }

    private function loadOffer(RepairRequestOffer $offer): RepairRequestOffer
    {
        return $offer->load([
            'artisan.artisanProfile.category',
            'repairRequest.category',
            'repairRequest.client',
        ]);
    }
}
