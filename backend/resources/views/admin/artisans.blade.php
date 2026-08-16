@extends('admin.layout')

@section('title', 'Artisans')
@section('page_title', 'Artisans')

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Nom</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Téléphone</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Métier</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Ville</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Quartier</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Vérification</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Note</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Avis</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Disponibilité</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Offres reçues</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Acceptées</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($artisans as $artisan)
                    @php($profile = $artisan->artisanProfile)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium text-slate-800">
                            <a href="{{ route('admin.artisans.show', $artisan) }}" class="hover:text-blue-700">{{ $artisan->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $artisan->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $profile?->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $profile?->city ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $profile?->district ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php($status = $profile?->verification_status ?? 'pending')
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $status === 'verified' ? 'bg-green-100 text-green-700' : ($status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $status === 'verified' ? 'Vérifié' : ($status === 'rejected' ? 'Rejeté' : 'En attente') }}
                            </span>
                            @if($artisan->pending_verifications > 0)
                                <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">dossier</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $artisan->average_rating !== null ? number_format($artisan->average_rating, 1) : '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $artisan->reviews_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $profile?->is_available ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-700' }}">
                                {{ $profile?->is_available ? 'Disponible' : 'Indisponible' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $artisan->offers_count }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $artisan->accepted_offers_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $artisan->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $artisan->is_active ? 'Actif' : 'Désactivé' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.artisans.show', $artisan) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-300 block">
                                Voir la fiche
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="13" class="px-4 py-10 text-center text-slate-500">Aucun artisan inscrit.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection