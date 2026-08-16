@extends('admin.layout')

@section('title', 'Avis')
@section('page_title', 'Avis')

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Client</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Artisan</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Référence</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Note</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Commentaire</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reviews as $review)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $review->client?->name ?? 'Client supprimé' }}</td>
                        <td class="px-4 py-3 text-slate-800">{{ $review->artisan?->name ?? 'Artisan supprimé' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $review->repairRequest?->reference ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                @for($i = 1; $i <= $review->rating; $i++)
                                    <svg class="h-4 w-4 fill-amber-400" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279L12 17.772l-7.416 3.945 1.48-8.279L3.332 8.756z"/></svg>
                                @endfor
                                @for($i = $review->rating + 1; $i <= 5; $i++)
                                    <svg class="h-4 w-4 fill-slate-200" viewBox="0 0 24 24"><path d="M12 .587l3.668 7.568 8.332 1.151-6.064 5.828 1.48 8.279L12 17.772l-7.416 3.945 1.48-8.279L3.332 8.756z"/></svg>
                                @endfor
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-600 max-w-xs truncate">{{ $review->comment ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $review->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr class="border-t border-slate-200">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">Aucun avis pour le moment.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
