<?php

namespace Tests\Concerns;

use App\Models\ArtisanProfile;
use App\Models\User;

trait InteractsWithArtisans
{
    private function createArtisan(array $userAttributes = [], array $profileAttributes = []): User
    {
        $artisan = $this->user(array_merge(['role' => 'artisan'], $userAttributes));

        $categoryId = $profileAttributes['category_id'] ?? $this->plumbing->id;
        $city = array_key_exists('city', $profileAttributes) ? $profileAttributes['city'] : 'Cotonou';
        $district = array_key_exists('district', $profileAttributes) ? $profileAttributes['district'] : 'Akpakpa';

        ArtisanProfile::create(array_merge([
            'user_id' => $artisan->id,
            'category_id' => $categoryId,
            'city' => $city,
            'district' => $district,
            'description' => 'Dépanneur disponible à Cotonou.',
            'is_available' => true,
            'verification_status' => ArtisanProfile::VERIFICATION_VERIFIED,
        ], $profileAttributes));

        $artisan->categories()->attach($categoryId, ['is_primary' => true]);
        $artisan->serviceAreas()->create(['city' => $city, 'district' => $district]);

        return $artisan;
    }
}
