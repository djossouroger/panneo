@extends('admin.layout')

@section('title', 'Litige #'.$dispute->id)
@section('page_title', 'Litige')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.disputes') }}" class="text-sm font-medium text-blue-700 hover:text-blue-800">Retour aux litiges</a>
</div>

<div class="grid gap-4 xl:grid-cols-3">
    <div class="xl:col-span-2 space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-900">{{ $dispute->subject }}</h3>
                <span class="rounded-full px-3 py-1 text-xs font-medium {{ $dispute->status === 'resolved' ? 'bg-green-100 text-green-700' : ($dispute->status === 'rejected' ? 'bg-slate-100 text-slate-700' : ($dispute->status === 'in_review' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700')) }}">
                    {{ $dispute->status === 'open' ? 'Ouvert' : ($dispute->status === 'in_review' ? 'En examen' : ($dispute->status === 'resolved' ? 'Résolu' : 'Rejeté')) }}
                </span>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-slate-500">Demande</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $dispute->repairRequest?->reference }} — {{ $dispute->repairRequest?->title }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Métier</p>
                    <p class="mt-1 font-semibold text-slate-900">{{ $dispute->repairRequest?->category?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Client</p>
                    <p class="mt-1 text-slate-700">{{ $dispute->repairRequest?->client?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Artisan</p>
                    <p class="mt-1 text-slate-700">{{ $dispute->repairRequest?->acceptedArtisan?->name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Type</p>
                    <p class="mt-1 text-slate-700">{{ $dispute->type }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-500">Signaleur</p>
                    <p class="mt-1 text-slate-700">{{ $dispute->reporter?->name }}</p>
                </div>
            </div>

            <div class="mt-4">
                <p class="text-sm font-medium text-slate-500">Description</p>
                <p class="mt-1 whitespace-pre-line text-slate-700">{{ $dispute->description }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Décision</h3>
            @if($dispute->resolution_notes)
                <div class="mt-3">
                    <p class="text-sm font-medium text-slate-500">Notes de résolution (visibles par les parties)</p>
                    <p class="mt-1 whitespace-pre-line text-slate-700">{{ $dispute->resolution_notes }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.disputes.update', $dispute) }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label for="status" class="block text-sm font-medium text-slate-700">Statut</label>
                    <select name="status" id="status"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <option value="open" @selected($dispute->status === 'open')>Ouvert</option>
                        <option value="in_review" @selected($dispute->status === 'in_review')>En cours d’examen</option>
                        <option value="resolved" @selected($dispute->status === 'resolved')>Résolu</option>
                        <option value="rejected" @selected($dispute->status === 'rejected')>Rejeté</option>
                    </select>
                </div>
                <div>
                    <label for="resolution_notes" class="block text-sm font-medium text-slate-700">Notes de résolution (visibles par les parties)</label>
                    <textarea name="resolution_notes" id="resolution_notes" rows="3" maxlength="2000"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">{{ $dispute->resolution_notes }}</textarea>
                </div>
                <div>
                    <label for="admin_notes" class="block text-sm font-medium text-slate-700">Notes internes (confidentielles)</label>
                    <textarea name="admin_notes" id="admin_notes" rows="2" maxlength="2000"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">{{ $dispute->admin_notes }}</textarea>
                </div>
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Enregistrer</button>
            </form>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Informations</h3>
            <div class="mt-4 space-y-3 text-sm">
                <p class="text-slate-600">Créé le {{ $dispute->created_at->format('d/m/Y H:i') }}</p>
                <p class="text-slate-600">Résolu le {{ $dispute->resolved_at?->format('d/m/Y H:i') ?? '—' }}</p>
                <p class="text-slate-600">Par {{ $dispute->resolver?->name ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
