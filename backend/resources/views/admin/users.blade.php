@extends('admin.layout')

@section('title', 'Utilisateurs')
@section('page_title', 'Utilisateurs')

@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-700">Nom</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Email</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Téléphone</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Rôle</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Statut</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Inscription</th>
                    <th class="px-4 py-3 font-semibold text-slate-700">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr class="border-t border-slate-200">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $user->phone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $user->role === 'client' ? 'bg-blue-100 text-blue-700' : ($user->role === 'artisan' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-700') }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $user->is_active ? 'Actif' : 'Désactivé' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->created_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            @if(auth()->id() !== $user->id)
                                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:border-slate-300">
                                        {{ $user->is_active ? 'Désactiver' : 'Réactiver' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
