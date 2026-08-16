@extends('admin.layout')

@section('title', $artisan->name)
@section('page_title', 'Artisan')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.artisans') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Retour aux artisans</a>
</div>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h2 class="text-2xl font-bold text-blue-700">{{ $artisan->name }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ $artisan->email }}</p>
    </div>
    @php($profile = $artisan->artisanProfile)
    @php($latestSubmission = $artisan->verificationSubmissions->first())
    @php($status = $profile?->verification_status ?? 'pending')
    <div class="flex items-center gap-2">
        <span class="rounded-full px-3 py-1 text-xs font-medium {{ $status === 'verified' ? 'bg-green-100 text-green-700' : ($status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
            {{ $status === 'verified' ? 'Vérifié par Pannéo' : ($status === 'rejected' ? 'Profil rejeté' : 'En attente de vérification') }}
        </span>
        @if($latestSubmission && $latestSubmission->isPending())
            <a href="{{ route('admin.verifications.show', $latestSubmission) }}"
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                Examiner la demande
            </a>
        @endif
    </div>
</div>

<div class="grid gap-4 xl:grid-cols-3">
    <div class="xl:col-span-2 space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Profil</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Nom</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $artisan->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Téléphone</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $artisan->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Email</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $artisan->email }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Métier</p>
                    <p class="mt-1 text-slate-700">{{ $profile?->category?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Ville</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $profile?->city ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Quartier</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $profile?->district ?? '—' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm font-medium text-slate-500">À propos</p>
                    <p class="mt-1 text-slate-700 whitespace-pre-line">{{ $profile?->description ?: '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Statut</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Actif</p>
                    <p class="mt-1 font-semibold {{ $artisan->is_active ? 'text-emerald-700' : 'text-slate-700' }}">
                        {{ $artisan->is_active ? 'Oui' : 'Non' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Disponibilité</p>
                    <p class="mt-1 font-semibold {{ $profile?->is_available ? 'text-emerald-700' : 'text-slate-700' }}">
                        {{ $profile ? ($profile->is_available ? 'Disponible' : 'Indisponible') : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Vérifié par Pannéo</p>
                    <p class="mt-1 font-semibold {{ $status === 'verified' ? 'text-emerald-700' : 'text-slate-700' }}">
                        {{ $status === 'verified' ? 'Oui' : ($status === 'rejected' ? 'Non (rejeté)' : 'En attente') }}
                    </p>
                </div>
                @if($profile?->verified_at)
                    <div>
                        <p class="text-sm font-medium text-slate-500">Vérifié le</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $profile->verified_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-900">Demandes de vérification</h3>
                <a href="{{ route('admin.verifications') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Toutes</a>
            </div>

            @forelse($artisan->verificationSubmissions as $submission)
                <div class="mt-4 border-t border-slate-200 pt-4 first:mt-0 first:border-t-0 first:pt-0">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <p class="font-medium text-slate-800">
                                #{{ $submission->id }}
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium ml-1 {{ $submission->status === 'approved' ? 'bg-green-100 text-green-700' : ($submission->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                    {{ $submission->status === 'approved' ? 'Approuvée' : ($submission->status === 'rejected' ? 'Refusée' : 'En attente') }}
                                </span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ $submission->created_at->format('d/m/Y H:i') }} · {{ $submission->documents->count() }} document(s)</p>
                            @if($submission->rejection_reason)
                                <p class="mt-1 text-xs text-red-600">Motif : {{ $submission->rejection_reason }}</p>
                            @endif
                        </div>
                        <a href="{{ route('admin.verifications.show', $submission) }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Voir</a>
                    </div>
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500">Aucune demande de vérification.</p>
            @endforelse
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-900">Avis récents</h3>
                <a href="{{ route('admin.reviews') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Tous les avis</a>
            </div>

            @forelse($recentReviews as $review)
                <div class="mt-4 border-t border-slate-200 pt-4 first:mt-0 first:border-t-0 first:pt-0">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-slate-800">{{ $review->client?->name ?? 'Client supprimé' }}</p>
                        <div class="flex items-center gap-1">
                            @for($i = 1; $i <= $review->rating; $i++)
                                <svg class="h-4 w-4 fill-amber-400" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279L12 17.772l-7.416 3.945 1.48-8.279L3.332 8.756z"/></svg>
                            @endfor
                            @for($i = $review->rating + 1; $i <= 5; $i++)
                                <svg class="h-4 w-4 fill-slate-200" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279L12 17.772l-7.416 3.945 1.48-8.279L3.332 8.756z"/></svg>
                            @endfor
                        </div>
                    </div>
                    @if($review->comment)
                        <p class="mt-1 text-sm text-slate-600">{{ $review->comment }}</p>
                    @endif
                    <p class="mt-1 text-xs text-slate-400">{{ $review->created_at->format('d/m/Y H:i') }}</p>
                </div>
            @empty
                <p class="mt-2 text-sm text-slate-500">Aucun avis pour le moment.</p>
            @endforelse
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Activité</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Interventions terminées</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $artisan->completed_interventions }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Note moyenne</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">
                        {{ $artisan->average_rating !== null ? number_format($artisan->average_rating, 1) : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Nombre d’avis</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $artisan->reviews_count }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Offres</h3>
            <div class="mt-4 space-y-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Offres reçues</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $artisan->offers_count }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Offres acceptées</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $artisan->accepted_offers_count }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
