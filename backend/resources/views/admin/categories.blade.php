@extends('admin.layout')

@section('title', 'Catégories')
@section('page_title', 'Catégories')

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach($categories as $category)
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">{{ $category->name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $category->slug }}</p>
                </div>
                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $category->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                    {{ $category->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </div>

            <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Nom</label>
                        <input type="text" name="name" value="{{ $category->name }}" maxlength="100"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Devise</label>
                        <input type="text" name="currency" value="{{ $category->currency ?? 'XOF' }}" maxlength="5"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Prix indicatif min</label>
                        <input type="number" name="indicative_min_price" value="{{ $category->indicative_min_price }}" min="0"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Prix indicatif max</label>
                        <input type="number" name="indicative_max_price" value="{{ $category->indicative_max_price }}" min="0"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Frais de déplacement</label>
                        <input type="number" name="callout_fee" value="{{ $category->callout_fee }}" min="0"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600">Libellé (ex : Déplacement)</label>
                        <input type="text" name="callout_fee_label" value="{{ $category->callout_fee_label }}" maxlength="60"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)
                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    Catégorie active
                </label>

                <button type="submit" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Enregistrer</button>
            </form>
        </div>
    @endforeach
</div>
@endsection
