<?php

namespace App\Http\Resources;

use App\Models\ArtisanProfile;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ArtisanProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $profile = $user->artisanProfile;

        $categories = $profile
            ? $profile->relationLoaded('categories') ? $profile->categories : $profile->categories()->get()
            : collect();

        $areas = $profile && $profile->relationLoaded('serviceAreas')
            ? $profile->serviceAreas
            : ($profile ? $profile->serviceAreas()->get() : collect());

        $hours = $profile && $profile->relationLoaded('workingHours')
            ? $profile->workingHours
            : ($profile ? $profile->workingHours()->get() : collect());

        $unavailabilities = $profile && $profile->relationLoaded('unavailabilities')
            ? $profile->unavailabilities
            : ($profile ? $profile->unavailabilities()->get() : collect());

        $portfolio = $profile && $profile->relationLoaded('portfolioItems')
            ? $profile->portfolioItems
            : ($profile ? $profile->portfolioItems()->get() : collect());

        $submission = $profile && $profile->relationLoaded('latestVerificationSubmission')
            ? $profile->latestVerificationSubmission
            : ($profile ? $profile->latestVerificationSubmission()->get()->first() : null);

        $matching = app(MatchingService::class);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
            'role' => $user->role,
            'profile' => [
                'id' => $profile?->id,
                'description' => $profile?->description,
                'city' => $profile?->city,
                'district' => $profile?->district,
                'is_available' => (bool) $profile?->is_available,
                'verification_status' => $profile?->verification_status ?? ArtisanProfile::VERIFICATION_PENDING,
                'profile_photo_url' => $profile?->profile_photo_path ? url('storage/'.$profile->profile_photo_path) : null,
                'years_of_experience' => $profile?->years_of_experience,
                'specialties' => $profile?->specialties ?? [],
                'categories' => $categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'icon' => $category->icon,
                    'slug' => $category->slug,
                    'is_primary' => (bool) ($category->pivot?->is_primary ?? false),
                ])->values(),
                'service_areas' => $areas->map(fn ($area) => [
                    'id' => $area->id,
                    'city' => $area->city,
                    'district' => $area->district,
                ])->values(),
                'working_hours' => $hours->map(fn ($hour) => [
                    'day_of_week' => (int) $hour->day_of_week,
                    'start_time' => $hour->start_time,
                    'end_time' => $hour->end_time,
                    'is_working_day' => (bool) $hour->is_working_day,
                ])->values(),
                'unavailabilities' => $unavailabilities->map(fn ($unavailability) => [
                    'id' => $unavailability->id,
                    'type' => $unavailability->type,
                    'starts_at' => $unavailability->starts_at?->toISOString(),
                    'ends_at' => $unavailability->ends_at?->toISOString(),
                    'reason' => $unavailability->reason,
                    'is_active' => $unavailability->isActiveAt(now()),
                ])->values(),
                'portfolio' => $portfolio->map(fn ($item) => [
                    'id' => $item->id,
                    'image_url' => url('storage/'.$item->image_path),
                    'caption' => $item->caption,
                ])->values(),
                'verification' => $submission ? [
                    'submission_status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toISOString(),
                    'reviewed_at' => $submission->reviewed_at?->toISOString(),
                    'rejection_reason' => $submission->rejection_reason,
                    'documents_count' => $submission->relationLoaded('documents') ? $submission->documents->count() : $submission->documents()->count(),
                ] : [
                    'submission_status' => null,
                    'submitted_at' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'documents_count' => 0,
                ],
            ],
            'within_working_hours' => $user->role === 'artisan' && $matching->isWithinWorkingHours($user),
            'stats' => [
                'completed_interventions' => $user->completedInterventionsCount(),
                'average_rating' => $user->averageRating(),
                'reviews_count' => $user->reviewsCount(),
            ],
        ];
    }
}
