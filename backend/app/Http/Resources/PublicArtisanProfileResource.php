<?php

namespace App\Http\Resources;

use App\Models\ArtisanProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicArtisanProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->artisanProfile;

        $categories = $profile
            ? $profile->relationLoaded('categories') ? $profile->categories : $profile->categories()->get()
            : collect();

        $areas = $profile && $profile->relationLoaded('serviceAreas')
            ? $profile->serviceAreas
            : ($profile ? $profile->serviceAreas()->get() : collect());

        $portfolio = $profile && $profile->relationLoaded('portfolioItems')
            ? $profile->portfolioItems
            : ($profile ? $profile->portfolioItems()->get() : collect());

        $verificationStatus = $profile?->verification_status ?? ArtisanProfile::VERIFICATION_PENDING;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'profile' => [
                'categories' => $categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'slug' => $category->slug,
                    'is_primary' => (bool) ($category->pivot?->is_primary ?? false),
                    'indicative_min_price' => $category->indicative_min_price,
                    'indicative_max_price' => $category->indicative_max_price,
                    'currency' => $category->currency,
                ])->values(),
                'city' => $profile?->city,
                'district' => $profile?->district,
                'description' => $profile?->description,
                'is_available' => (bool) $profile?->is_available,
                'verification_status' => $verificationStatus,
                'verified_label' => $this->verifiedLabel($verificationStatus),
                'profile_photo_url' => $profile?->profile_photo_path ? url('storage/'.$profile->profile_photo_path) : null,
                'years_of_experience' => $profile?->years_of_experience,
                'specialties' => $profile?->specialties ?? [],
                'service_areas' => $areas->map(fn ($area) => [
                    'id' => $area->id,
                    'city' => $area->city,
                    'district' => $area->district,
                ])->values(),
                'portfolio' => $portfolio->take(3)->map(fn ($item) => [
                    'id' => $item->id,
                    'image_url' => url('storage/'.$item->image_path),
                    'caption' => $item->caption,
                ])->values(),
            ],
            'stats' => [
                'completed_interventions' => $this->completedInterventionsCount(),
                'average_rating' => $this->averageRating(),
                'reviews_count' => $this->reviewsCount(),
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
