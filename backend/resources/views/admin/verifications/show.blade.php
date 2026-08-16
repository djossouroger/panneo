@extends('admin.layout')

@section('title', 'Demande #'.$submission->id)
@section('page_title', 'Demande de vérification')

@section('content')
@php
    $profile = $submission->artisan?->artisanProfile;
@endphp
<div class="mb-4">
    <a href="{{ route('admin.verifications') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Retour aux vérifications</a>
</div>

<div class="grid gap-4 xl:grid-cols-3">
    <div class="xl:col-span-2 space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-900">Vérification d’identité</h3>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $submission->status === 'approved' ? 'bg-green-100 text-green-700' : ($submission->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                    {{ $submission->status === 'approved' ? 'Approuvée' : ($submission->status === 'rejected' ? 'Refusée' : 'En attente') }}
                </span>
            </div>

            @php
                $labelForType = [
                    'identity_document' => 'Pièce d’identité',
                    'selfie' => 'Selfie avec pièce',
                    'professional_proof' => 'Justificatif professionnel',
                ];
                $imageDocs = $submission->documents->filter(fn ($doc) => str_starts_with((string) $doc->mime_type, 'image/'));
            @endphp

            @if($imageDocs->isNotEmpty())
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($imageDocs as $document)
                        <div class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-700">{{ $labelForType[$document->document_type] ?? $document->original_name }}</p>
                                <a href="{{ route('admin.verifications.documents.image', $document) }}" target="_blank"
                                    class="text-xs font-medium text-blue-700 hover:text-blue-800">Agrandir</a>
                            </div>
                            <a href="{{ route('admin.verifications.documents.image', $document) }}" target="_blank" class="block bg-slate-100">
                                <img src="{{ route('admin.verifications.documents.image', $document) }}" alt="{{ $document->original_name }}"
                                    class="mx-auto max-h-72 w-auto object-contain" />
                            </a>
                            <div class="flex items-center justify-between bg-white px-3 py-2">
                                <p class="text-xs text-slate-500">{{ $document->original_name }} · {{ round($document->file_size / 1024) }} Ko</p>
                                <a href="{{ route('admin.verifications.documents.download', $document) }}"
                                    class="text-xs font-medium text-blue-700 hover:text-blue-800">Télécharger</a>
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">Comparez la pièce d’identité et le selfie (comparaison visuelle, sans reconnaissance faciale automatique). Cliquez sur une image pour l’agrandir.</p>
            @endif

            @if($submission->documents->count() > $imageDocs->count())
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    @foreach($submission->documents->whereNotIn('id', $imageDocs->pluck('id')) as $document)
                        <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4">
                            <div class="min-w-0">
                                <p class="font-medium text-slate-800">{{ $document->original_name }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    {{ $labelForType[$document->document_type] ?? 'Document' }}
                                    · {{ round($document->file_size / 1024) }} Ko
                                </p>
                            </div>
                            <a href="{{ route('admin.verifications.documents.download', $document) }}"
                                class="ml-3 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-blue-700 hover:border-blue-300">Télécharger</a>
                        </div>
                    @endforeach
                </div>
            @elseif($imageDocs->isEmpty())
                <p class="mt-4 text-sm text-slate-500">Aucun document.</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Profil de l’artisan</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Nom</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $submission->artisan?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Téléphone</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $submission->artisan?->phone }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Métiers</p>
                    <p class="mt-1 text-slate-700">
                        {{ $profile?->categories->pluck('name')->join(', ') ?: '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Zones</p>
                    <p class="mt-1 text-slate-700">
                        {{ $profile?->serviceAreas->map(fn ($a) => trim(($a->district ?? '').' '.$a->city))->join(', ') ?: '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Années d’expérience</p>
                    <p class="mt-1 text-slate-700">{{ $profile?->years_of_experience ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Spécialités</p>
                    <p class="mt-1 text-slate-700">{{ collect($profile?->specialties ?? [])->join(', ') ?: '—' }}</p>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-sm font-medium text-slate-500">À propos</p>
                <p class="mt-1 whitespace-pre-line text-slate-700">{{ $profile?->description ?: '—' }}</p>
            </div>
        </div>

        @if($submission->rejection_reason)
            <div class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
                <h3 class="text-lg font-bold text-red-800">Motif du refus</h3>
                <p class="mt-2 text-sm text-red-700">{{ $submission->rejection_reason }}</p>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Décision</h3>
            <p class="mt-2 text-sm text-slate-600">Soumise le {{ $submission->created_at->format('d/m/Y H:i') }}.</p>

            @if($submission->isPending())
                <input type="checkbox" id="approve-modal" class="peer hidden" />
                <label for="approve-modal" class="mt-4 block w-full cursor-pointer rounded-xl bg-green-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-green-700">Valider cet artisan ?</label>

                <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 peer-checked:flex">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <h4 class="text-lg font-bold text-slate-900">Valider cet artisan ?</h4>
                        <p class="mt-2 text-sm text-slate-600">
                            Le profil {{ $submission->artisan?->name }} sera marqué « Vérifié par Pannéo » et
                            recevra la notification « Votre compte a été validé ». Il sera ensuite inclus dans les propositions de dépannage.
                        </p>
                        <div class="mt-5 flex justify-end gap-3">
                            <label for="approve-modal" class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-300">Annuler</label>
                            <form method="POST" action="{{ route('admin.verifications.approve', $submission) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Oui, valider</button>
                            </form>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.verifications.reject', $submission) }}" class="mt-3">
                    @csrf
                    <label for="reason" class="block text-sm font-medium text-slate-700">Motif du refus</label>
                    <textarea name="reason" id="reason" rows="3" required maxlength="500"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                        placeholder="Document illisible, pièce manquante…"></textarea>
                    <button type="submit" class="mt-3 w-full rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Refuser le dossier</button>
                </form>
            @elseif($submission->status === 'rejected')
                <form method="POST" action="{{ route('admin.verifications.reopen', $submission) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:border-slate-300">Repasser en attente</button>
                </form>
            @else
                <p class="mt-3 rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">Demande approuvée le {{ $submission->reviewed_at?->format('d/m/Y H:i') }}.</p>
            @endif
        </div>
    </div>
</div>
@endsection
