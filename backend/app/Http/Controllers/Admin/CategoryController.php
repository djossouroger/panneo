<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::orderBy('name')->get();

        return view('admin.categories', compact('categories'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'indicative_min_price' => ['nullable', 'integer', 'min:0'],
            'indicative_max_price' => ['nullable', 'integer', 'min:0', 'gte:indicative_min_price'],
            'callout_fee_label' => ['nullable', 'string', 'max:60'],
            'callout_fee' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:5'],
        ]);

        $category->forceFill([
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
            'indicative_min_price' => $validated['indicative_min_price'] ?? null,
            'indicative_max_price' => $validated['indicative_max_price'] ?? null,
            'callout_fee_label' => $validated['callout_fee_label'] ?? null,
            'callout_fee' => $validated['callout_fee'] ?? null,
            'currency' => $validated['currency'] ?? 'XOF',
        ])->save();

        return back()->with('success', 'Catégorie mise à jour.');
    }
}
