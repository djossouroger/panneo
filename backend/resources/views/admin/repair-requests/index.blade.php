@extends('admin.layout')

@section('title', 'Demandes')
@section('page_title', 'Demandes')

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
@endphp

<form method="GET" action="{{ route('admin.repair-requests.index') }}" class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid gap-3 md:grid-cols-3">
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Statut</label>
            <select name="status" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                <option value="all" @selected($selectedStatus === 'all')>Tous</option>
                @foreach($statusLabels as $status => $label)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Catégorie</label>
            <select name="category_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none">
                <option value="">Toutes</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) $selectedCategoryId === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Filtrer</button>
            <a href="{{ route('admin.repair-requests.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:border-slate-300">Réinitialiser</a>
        </div>
    </div>
</form>

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Référence</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Client</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Catégorie</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Artisan</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Ville / Quartier</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Date</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($repairRequests as $repairRequest)
                    @php($linkedArtisan = $repairRequest->acceptedArtisan ?? $repairRequest->activeOffer?->artisan)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-semibold text-blue-700">{{ $repairRequest->reference }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $repairRequest->client?->name ?? 'Client supprimé' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $repairRequest->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $linkedArtisan?->name ?? 'Non assigné' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $repairRequest->city }} / {{ $repairRequest->district }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $statusClasses[$repairRequest->status] ?? 'bg-slate-100 text-slate-700' }}">
                                {{ $statusLabels[$repairRequest->status] ?? $repairRequest->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $repairRequest->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.repair-requests.show', $repairRequest) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-300">Voir</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500">Aucune demande ne correspond aux filtres.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
