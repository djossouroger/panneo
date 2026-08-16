@extends('admin.layout')

@section('title', 'Vérifications')
@section('page_title', 'Vérifications des artisans')

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Artisan</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Téléphone</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Métier(s)</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Ville</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Date d’inscription</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($submissions as $submission)
                    @php($profile = $submission->artisan?->artisanProfile)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium text-slate-800">
                            <a href="{{ route('admin.verifications.show', $submission) }}" class="hover:text-blue-700">{{ $submission->artisan?->name ?? 'Artisan supprimé' }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $submission->artisan?->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $profile?->categories->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $profile?->city ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $submission->artisan?->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $submission->status === 'approved' ? 'bg-green-100 text-green-700' : ($submission->status === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $submission->status === 'approved' ? 'Approuvée' : ($submission->status === 'rejected' ? 'Refusée' : 'En attente') }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.verifications.show', $submission) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-300">
                                {{ $submission->isPending() ? 'Examiner' : 'Détails' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucune demande de vérification.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
