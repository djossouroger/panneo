<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\RepairRequest;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function store(Request $request, RepairRequest $repairRequest)
    {
        $user = $request->user();

        $isClient = $repairRequest->client_id === $user->id;
        $isArtisan = $repairRequest->accepted_artisan_id === $user->id;

        if (! $isClient && ! $isArtisan) {
            abort(403, 'Vous n’êtes pas partie prenante de cette demande.');
        }

        if (! in_array($repairRequest->status, [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS, RepairRequest::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages(['status' => ['Un litige ne peut être ouvert que pour une demande acceptée ou terminée.']]);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::in([Dispute::TYPE_BEHAVIOR, Dispute::TYPE_SERVICE_QUALITY, Dispute::TYPE_NO_SHOW, Dispute::TYPE_SAFETY, Dispute::TYPE_OTHER])],
        ]);

        $dispute = Dispute::create([
            'repair_request_id' => $repairRequest->id,
            'reporter_id' => $user->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'type' => $validated['type'],
            'status' => Dispute::STATUS_OPEN,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Litige signalé. Notre équipe va l’examiner.',
            'data' => $this->payload($dispute),
        ], 201);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $disputes = Dispute::with(['repairRequest.category', 'repairRequest.client:id,name', 'repairRequest.acceptedArtisan:id,name'])
            ->whereIn('repair_request_id', function ($query) use ($user) {
                $query->select('id')
                    ->from('repair_requests')
                    ->where('client_id', $user->id)
                    ->orWhere('accepted_artisan_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->paginate(ApiPagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Litiges récupérés.',
            'data' => $disputes->map(fn (Dispute $dispute) => $this->payload($dispute))->values(),
            'meta' => ApiPagination::meta($disputes),
        ]);
    }

    public function show(Request $request, Dispute $dispute)
    {
        if (! in_array($request->user()->id, $dispute->participantUserIds(), true)) {
            abort(403, 'Vous n’êtes pas partie prenante de ce litige.');
        }

        $dispute->load(['repairRequest.category', 'repairRequest.client:id,name', 'repairRequest.acceptedArtisan:id,name']);

        return response()->json([
            'success' => true,
            'message' => 'Litige récupéré.',
            'data' => $this->payload($dispute),
        ]);
    }

    private function payload(Dispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'subject' => $dispute->subject,
            'description' => $dispute->description,
            'type' => $dispute->type,
            'type_label' => match ($dispute->type) {
                Dispute::TYPE_BEHAVIOR => 'Comportement',
                Dispute::TYPE_SERVICE_QUALITY => 'Qualité du service',
                Dispute::TYPE_NO_SHOW => 'Absence du professionnel',
                Dispute::TYPE_SAFETY => 'Sécurité',
                default => 'Autre',
            },
            'status' => $dispute->status,
            'status_label' => match ($dispute->status) {
                Dispute::STATUS_IN_REVIEW => 'En cours d’examen',
                Dispute::STATUS_RESOLVED => 'Résolu',
                Dispute::STATUS_REJECTED => 'Rejeté',
                default => 'Ouvert',
            },
            'repair_request' => [
                'id' => $dispute->repairRequest?->id,
                'reference' => $dispute->repairRequest?->reference,
                'title' => $dispute->repairRequest?->title,
                'category' => $dispute->repairRequest?->category?->name,
            ],
            'resolution_notes' => $dispute->resolution_notes,
            'resolved_at' => $dispute->resolved_at?->toISOString(),
            'created_at' => $dispute->created_at?->toISOString(),
        ];
    }
}
