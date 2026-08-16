<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index()
    {
        $disputes = Dispute::with(['repairRequest.category', 'reporter'])
            ->orderByRaw("CASE WHEN status IN ('open', 'in_review') THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->get();

        return view('admin.disputes.index', compact('disputes'));
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(['repairRequest.category', 'repairRequest.client', 'repairRequest.acceptedArtisan', 'reporter', 'resolver']);

        return view('admin.disputes.show', compact('dispute'));
    }

    public function update(Request $request, Dispute $dispute)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Dispute::STATUS_OPEN, Dispute::STATUS_IN_REVIEW, Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED])],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasResolved = in_array($dispute->status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true);
        $isNowResolved = in_array($validated['status'], [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true);

        $dispute->forceFill([
            'status' => $validated['status'],
            'resolution_notes' => $validated['resolution_notes'] ?? $dispute->resolution_notes,
            'admin_notes' => $validated['admin_notes'] ?? $dispute->admin_notes,
            'resolved_at' => $isNowResolved && ! $wasResolved ? now() : $dispute->resolved_at,
            'resolved_by' => $isNowResolved && ! $wasResolved ? $request->user()->id : $dispute->resolved_by,
        ])->save();

        foreach ([$dispute->repairRequest?->client_id, $dispute->repairRequest?->accepted_artisan_id] as $participantId) {
            if (! $participantId) {
                continue;
            }

            $participant = User::find($participantId);
            if ($participant) {
                $this->notifications->send(
                    $participant,
                    Notification::TYPE_DISPUTE_STATUS_UPDATED,
                    'Mise à jour du litige',
                    sprintf('Le litige « %s » est passé au statut : %s.', $dispute->subject, $this->statusLabel($validated['status'])),
                    ['dispute_id' => $dispute->id]
                );
            }
        }

        return back()->with('success', 'Litige mis à jour.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Dispute::STATUS_IN_REVIEW => 'En cours d’examen',
            Dispute::STATUS_RESOLVED => 'Résolu',
            Dispute::STATUS_REJECTED => 'Rejeté',
            default => 'Ouvert',
        };
    }
}
