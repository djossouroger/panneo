<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()
            ->favoriteArtisans()
            ->with('artisanProfile.categories')
            ->withCount([
                'reviewsReceived as reviews_count',
                'acceptedRepairRequests as completed_interventions' => fn ($query) => $query->where('status', RepairRequest::STATUS_COMPLETED),
            ])
            ->withAvg('reviewsReceived as average_rating', 'rating')
            ->latest('favorite_artisans.created_at')
            ->paginate(ApiPagination::perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Artisans favoris récupérés.',
            'data' => $favorites->map(fn (User $artisan) => [
                'id' => $artisan->id,
                'name' => $artisan->name,
                'profile_photo_url' => $artisan->artisanProfile?->profile_photo_path ? url('storage/'.$artisan->artisanProfile->profile_photo_path) : null,
                'verification_status' => $artisan->artisanProfile?->verification_status,
                'primary_category' => $artisan->artisanProfile?->categories->firstWhere('pivot.is_primary')?->name,
                'city' => $artisan->artisanProfile?->city,
                'district' => $artisan->artisanProfile?->district,
                'is_available' => (bool) $artisan->artisanProfile?->is_available,
                'stats' => [
                    'completed_interventions' => (int) ($artisan->completed_interventions ?? 0),
                    'average_rating' => $artisan->average_rating !== null ? (float) $artisan->average_rating : null,
                    'reviews_count' => (int) ($artisan->reviews_count ?? 0),
                ],
            ])->values(),
            'meta' => ApiPagination::meta($favorites),
        ]);
    }

    public function toggle(Request $request, User $artisan)
    {
        $validated = $request->validate([
            'favorite' => ['required', 'boolean'],
        ]);

        if ($artisan->role !== 'artisan') {
            throw ValidationException::withMessages(['artisan_id' => ['Cet utilisateur n’est pas un dépanneur.']]);
        }

        $client = $request->user();

        if ($client->id === $artisan->id) {
            throw ValidationException::withMessages(['artisan_id' => ['Impossible d’ajouter votre propre profil.']]);
        }

        $hasCompletedIntervention = RepairRequest::where('client_id', $client->id)
            ->where('accepted_artisan_id', $artisan->id)
            ->where('status', RepairRequest::STATUS_COMPLETED)
            ->exists();

        if (! $hasCompletedIntervention) {
            throw ValidationException::withMessages(['artisan_id' => ['Vous ne pouvez ajouter un dépanneur qu’après une intervention terminée.']]);
        }

        if ($validated['favorite']) {
            $client->favoriteArtisans()->syncWithoutDetaching([$artisan->id]);
            $message = 'Dépanneur ajouté à vos favoris.';
        } else {
            $client->favoriteArtisans()->detach($artisan->id);
            $message = 'Dépanneur retiré de vos favoris.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['is_favorite' => (bool) $validated['favorite']],
        ]);
    }

    public function status(Request $request, User $artisan)
    {
        $isFavorite = $request->user()
            ->favoriteArtisans()
            ->whereKey($artisan->id)
            ->exists();

        return response()->json([
            'success' => true,
            'message' => 'Statut récupéré.',
            'data' => ['is_favorite' => $isFavorite],
        ]);
    }
}
