<?php

namespace App\Http\Requests\Api;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepairRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'client';
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists(Category::class, 'id')->where('is_active', true),
            ],
            'title' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:12', 'max:1200'],
            'city' => ['required', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:120'],
            'address_details' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array', 'min:1', 'max:2'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'La catégorie sélectionnée est indisponible.',
            'description.min' => 'Ajoutez quelques détails pour aider à comprendre la panne.',
            'district.required' => 'Le quartier est requis pour cette version.',
            'images.max' => 'Vous pouvez joindre 2 photos au maximum.',
            'images.*.mimes' => 'Seules les photos au format JPG, PNG ou WEBP sont acceptées.',
        ];
    }
}