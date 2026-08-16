<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function store(Request $request, RepairRequest $repairRequest)
    {
        if ($repairRequest->client_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez laisser un avis que sur vos propres demandes.');
        }

        if ($repairRequest->status !== RepairRequest::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => ['Vous ne pouvez laisser un avis qu’une fois l’intervention terminée.'],
            ]);
        }

        if ($repairRequest->accepted_artisan_id === null) {
            throw ValidationException::withMessages([
                'artisan' => ['Aucun artisan n’a été affecté à cette demande.'],
            ]);
        }

        if ($repairRequest->review()->exists()) {
            throw ValidationException::withMessages([
                'review' => ['Un avis a déjà été laissé pour cette intervention.'],
            ]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $review = Review::create([
            'repair_request_id' => $repairRequest->id,
            'client_id' => $request->user()->id,
            'artisan_id' => $repairRequest->accepted_artisan_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $this->notifications->send(
            $repairRequest->acceptedArtisan,
            Notification::TYPE_REVIEW_RECEIVED,
            'Nouvel avis reçu',
            'Un client a évalué une intervention terminée.',
            [
                'repair_request_id' => $repairRequest->id,
                'reference' => $repairRequest->reference,
                'rating' => (int) $review->rating,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Votre avis a bien été enregistré.',
            'data' => [
                'review' => [
                    'id' => $review->id,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at->toISOString(),
                ],
            ],
        ], 201);
    }

    public function showByRepairRequest(Request $request, RepairRequest $repairRequest)
    {
        if ($repairRequest->client_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez accéder qu’à vos propres demandes.');
        }

        $review = $repairRequest->review()->with('artisan')->first();

        if (! $review) {
            return response()->json([
                'success' => true,
                'message' => 'Aucun avis pour le moment.',
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avis récupéré.',
            'data' => [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'artisan' => [
                    'id' => $review->artisan->id,
                    'name' => $review->artisan->name,
                ],
                'created_at' => $review->created_at->toISOString(),
            ],
        ]);
    }
}
