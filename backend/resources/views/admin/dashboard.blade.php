@extends('admin.layout')

@section('title', 'Tableau de bord')
@section('page_title', 'Tableau de bord')

@section('content')
@php($cards = [
    ['label' => 'En recherche', 'value' => $stats['requests_searching'], 'color' => 'blue', 'icon' => 'search'],
    ['label' => 'En attente de réponse', 'value' => $stats['requests_awaiting_artisan'], 'color' => 'orange', 'icon' => 'clock'],
    ['label' => 'Interventions en cours', 'value' => $stats['requests_in_progress'], 'color' => 'blue', 'icon' => 'play'],
    ['label' => 'Terminées', 'value' => $stats['requests_completed'], 'color' => 'emerald', 'icon' => 'check'],
    ['label' => 'Artisans disponibles', 'value' => $stats['available_artisans'], 'color' => 'green', 'icon' => 'power'],
    ['label' => 'Avis reçus', 'value' => $stats['reviews_count'] ?? 0, 'color' => 'amber', 'icon' => 'star'],
])

<div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    @foreach($cards as $card)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-bold text-slate-900">{{ $card['value'] }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-{{ $card['color'] }}-100 text-{{ $card['color'] }}-600">
                    @switch($card['icon'])
                        @case('search')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            @break
                        @case('clock')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            @break
                        @case('play')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/></svg>
                            @break
@case('check')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2 4.8-5"/></svg>
                            @break
                        @case('power')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 2v8"/><path d="M18.4 5.6A9 9 0 1 1 5.6 18.4"/></svg>
                            @break
                        @case('star')
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14z"/></svg>
                            @break
                    @endswitch
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
