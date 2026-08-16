<?php

namespace App\Http\Resources;

use App\Models\ArtisanProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AvailableArtisanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->artisanProfile;
        $categories = $profile && $profile->relationLoaded('categories')
            ? $profile->categories
            : ($profile ? $profile->categories()->get() : collect());

        $primary = $categories->firstWhere('pivot.is_primary');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'is_primary' => (bool) ($category->pivot?->is_primary ?? false),
            ])->values(),
            'primary_category' => $primary ? [
                'id' => $primary->id,
                'name' => $primary->name,
                'icon' => $primary->icon,
            ] : null,
            'city' => $profile?->city,
            'district' => $profile?->district,
            'description' => $profile?->description,
            'is_available' => (bool) $profile?->is_available,
            'verification_status' => $profile?->verification_status ?? ArtisanProfile::VERIFICATION_PENDING,
            'verified_label' => $this->verifiedLabel($profile?->verification_status ?? ArtisanProfile::VERIFICATION_PENDING),
            'profile_photo_url' => $profile?->profile_photo_path ? url('storage/'.$profile->profile_photo_path) : null,
            'years_of_experience' => $profile?->years_of_experience,
            'specialties' => $profile?->specialties ?? [],
            'stats' => [
                'completed_interventions' => (int) ($this->completed_interventions ?? 0),
                'average_rating' => $this->average_rating !== null ? $this->average_rating : null,
                'reviews_count' => (int) ($this->reviews_count ?? 0),
            ],
        ];
    }

    private function verifiedLabel(string $status): string
    {
        return match ($status) {
            ArtisanProfile::VERIFICATION_VERIFIED => 'Vérifié par Pannéo',
            ArtisanProfile::VERIFICATION_REJECTED => 'Profil rejeté',
            default => 'Profil non encore vérifié',
        };
    }
}
