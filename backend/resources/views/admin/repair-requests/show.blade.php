@extends('admin.layout')

@section('title', $repairRequest->reference)
@section('page_title', 'Détail demande')

@section('content')
@php
    $statusLabels = [
        'pending' => 'En recherche',
        'awaiting_artisan' => 'Réponse en attente',
        'accepted' => 'Artisan trouvé',
        'in_progress' => 'Intervention en cours',
        'completed' => 'Terminée',
        'cancelled' => 'Annulée',
    ];
    $statusClasses = [
        'pending' => 'bg-blue-100 text-blue-700',
        'awaiting_artisan' => 'bg-orange-100 text-orange-700',
        'accepted' => 'bg-green-100 text-green-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-slate-100 text-slate-700',
    ];
    $offerLabels = [
        'pending' => 'À répondre',
        'accepted' => 'Acceptée',
        'rejected' => 'Refusée',
        'cancelled' => 'Annulée',
    ];
    $offerClasses = [
        'pending' => 'bg-orange-100 text-orange-700',
        'accepted' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-slate-100 text-slate-700',
    ];
    $artisanProfile = $repairRequest->acceptedArtisan?->artisanProfile;
    $timeline = [
        ['label' => 'Demande créée', 'date' => $repairRequest->created_at, 'done' => true],
        ['label' => 'Offre envoyée', 'date' => $repairRequest->offers->first()?->created_at, 'done' => $repairRequest->offers->isNotEmpty()],
        ['label' => 'Artisan accepté', 'date' => $repairRequest->accepted_at, 'done' => filled($repairRequest->accepted_at)],
        ['label' => 'Intervention commencée', 'date' => $repairRequest->started_at, 'done' => filled($repairRequest->started_at)],
        ['label' => 'Intervention terminée', 'date' => $repairRequest->completed_at, 'done' => filled($repairRequest->completed_at)],
    ];
@endphp

<div class="mb-4">
    <a href="{{ route('admin.repair-requests.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Retour aux demandes</a>
</div>

<div class="mb-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">Référence</p>
            <h2 class="mt-1 text-2xl font-bold text-blue-700">{{ $repairRequest->reference }}</h2>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusClasses[$repairRequest->status] ?? 'bg-slate-100 text-slate-700' }}">
            {{ $statusLabels[$repairRequest->status] ?? $repairRequest->status }}
        </span>
    </div>
</div>

<div class="grid gap-4 xl:grid-cols-3">
    <div class="xl:col-span-2 space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Demande</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Catégorie</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->category?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Date de création</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Titre</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->title ?: '—' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Description</p>
                    <p class="mt-1 whitespace-pre-line text-slate-700">{{ $repairRequest->description }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Ville</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->city }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Quartier</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->district }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">Indication</p>
                    <p class="mt-1 text-slate-700">{{ $repairRequest->address_details ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Intervention</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                <div>
                    <p class="text-sm font-medium text-slate-500">Acceptée le</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->accepted_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Commencée le</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->started_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Terminée le</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->completed_at?->format('d/m/Y H:i') ?? '—' }}</p>
                </div>
            </div>

            <div class="mt-6 space-y-4">
                @foreach($timeline as $step)
                    <div class="flex gap-3">
                        <div class="mt-1 flex h-6 w-6 items-center justify-center rounded-full {{ $step['done'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400' }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">{{ $step['label'] }}</p>
                            <p class="text-sm text-slate-500">{{ $step['date'] ? $step['date']->format('d/m/Y H:i') : '—' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-900">Mise en relation</h3>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $repairRequest->offers->count() }} proposition(s)</span>
            </div>

            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 font-semibold text-slate-700">Artisan</th>
                            <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                            <th class="px-4 py-3 font-semibold text-slate-700">Envoyée le</th>
                            <th class="px-4 py-3 font-semibold text-slate-700">Réponse le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($repairRequest->offers as $offer)
                            <tr class="border-t border-slate-200">
                                <td class="px-4 py-3 font-medium text-slate-800">{{ $offer->artisan?->name ?? 'Artisan supprimé' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium {{ $offerClasses[$offer->status] ?? 'bg-slate-100 text-slate-700' }}">
                                        {{ $offerLabels[$offer->status] ?? $offer->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $offer->created_at?->format('d/m H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $offer->responded_at?->format('d/m H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-slate-200">
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune proposition envoyée.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Client</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Nom</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->client?->name ?? 'Client supprimé' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Téléphone</p>
                    <p class="mt-1 text-slate-700">{{ $repairRequest->client?->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Email</p>
                    <p class="mt-1 text-slate-700">{{ $repairRequest->client?->email ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Artisan</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Nom</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $repairRequest->acceptedArtisan?->name ?? 'Non assigné' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Métier</p>
                    <p class="mt-1 text-slate-700">{{ $artisanProfile?->category?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Téléphone</p>
                    <p class="mt-1 text-slate-700">{{ $repairRequest->acceptedArtisan?->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Disponibilité actuelle</p>
                    <p class="mt-1 font-semibold {{ $artisanProfile?->is_available ? 'text-emerald-700' : 'text-slate-700' }}">
                        {{ $artisanProfile ? ($artisanProfile->is_available ? 'Disponible' : 'Indisponible') : '—' }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
