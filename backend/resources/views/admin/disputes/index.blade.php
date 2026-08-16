@extends('admin.layout')

@section('title', 'Litiges')
@section('page_title', 'Litiges')

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
@endif

<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Référence</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Sujet</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Type</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Signaleur</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Créé le</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($disputes as $dispute)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $dispute->repairRequest?->reference ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <a href="{{ route('admin.disputes.show', $dispute) }}" class="hover:text-blue-700">{{ $dispute->subject }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $dispute->type }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $dispute->reporter?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $dispute->status === 'resolved' ? 'bg-green-100 text-green-700' : ($dispute->status === 'rejected' ? 'bg-slate-100 text-slate-700' : ($dispute->status === 'in_review' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700')) }}">
                                {{ $dispute->status === 'open' ? 'Ouvert' : ($dispute->status === 'in_review' ? 'En examen' : ($dispute->status === 'resolved' ? 'Résolu' : 'Rejeté')) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $dispute->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.disputes.show', $dispute) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-300">Gérer</a>
                        </td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">Aucun litige.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
