<?php

namespace App\Services;

use App\Models\ArtisanProfile;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MatchingService
{
    public function candidateArtisans(RepairRequest $repairRequest, array $excludedArtisanIds = []): Collection
    {
        $excludedArtisanIds = array_values(array_filter(array_map('intval', $excludedArtisanIds)));

        $artisans = User::query()
            ->where('role', 'artisan')
            ->where('is_active', true)
            ->whereHas('artisanProfile', function ($query) {
                $query->where('is_available', true)
                    ->where('verification_status', ArtisanProfile::VERIFICATION_VERIFIED);
            })
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $repairRequest->category_id))
            ->whereHas('serviceAreas', function ($query) use ($repairRequest) {
                $query->whereRaw('LOWER(city) = LOWER(?)', [trim($repairRequest->city)]);

                $query->where(function ($zone) use ($repairRequest) {
                    $zone->whereNull('district');

                    $district = trim((string) $repairRequest->district);
                    if ($district !== '') {
                        $zone->orWhereRaw('LOWER(district) = LOWER(?)', [$district]);
                    }
                });
            })
            ->whereDoesntHave('acceptedRepairRequests', function ($query) {
                $query->whereIn('status', [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS]);
            })
            ->when($excludedArtisanIds !== [], fn ($query) => $query->whereNotIn('id', $excludedArtisanIds))
            ->with([
                'artisanProfile.categories',
                'artisanProfile.serviceAreas',
                'workingHours',
                'unavailabilities',
            ])
            ->withCount([
                'reviewsReceived as reviews_count',
                'acceptedRepairRequests as completed_interventions' => fn ($query) => $query->where('status', RepairRequest::STATUS_COMPLETED),
            ])
            ->withAvg('reviewsReceived as average_rating', 'rating')
            ->get();

        $available = $artisans->filter(fn (User $artisan) => $this->isCurrentlyAvailable($artisan));

        return $available->sortBy(function (User $artisan) {
            $rating = $artisan->average_rating ?? 0;
            $availability = (bool) ($artisan->artisanProfile?->is_available);

            return [-$rating, ! $availability, mb_strtolower($artisan->name)];
        })->values();
    }

    public function isCompatible(User $artisan, RepairRequest $repairRequest): bool
    {
        if ($artisan->role !== 'artisan' || ! $artisan->is_active) {
            return false;
        }

        $profile = $artisan->artisanProfile;

        if (! $profile || ! $profile->is_available || $profile->verification_status !== ArtisanProfile::VERIFICATION_VERIFIED) {
            return false;
        }

        $hasCategory = $artisan->relationLoaded('categories')
            ? $artisan->categories->contains('id', $repairRequest->category_id)
            : $artisan->categories()->whereKey($repairRequest->category_id)->exists();

        if (! $hasCategory) {
            return false;
        }

        if (! $this->hasCompatibleZone($artisan, $repairRequest)) {
            return false;
        }

        return $this->isCurrentlyAvailable($artisan);
    }

    public function hasCompatibleZone(User $artisan, RepairRequest $repairRequest): bool
    {
        $city = trim(mb_strtolower((string) $repairRequest->city));
        $district = trim(mb_strtolower((string) $repairRequest->district));

        if ($city === '') {
            return false;
        }

        $areas = $artisan->relationLoaded('serviceAreas')
            ? $artisan->serviceAreas
            : $artisan->serviceAreas()->get();

        return $areas->contains(function ($area) use ($city, $district) {
            if (mb_strtolower(trim((string) $area->city)) !== $city) {
                return false;
            }

            if ($area->district === null || trim($area->district) === '') {
                return true;
            }

            if ($district === '') {
                return false;
            }

            return mb_strtolower(trim((string) $area->district)) === $district;
        });
    }

    public function isCurrentlyAvailable(User $artisan, ?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        if ($this->hasActiveIntervention($artisan)) {
            return false;
        }

        if ($this->hasActiveUnavailability($artisan, $moment)) {
            return false;
        }

        return $this->isWithinWorkingHours($artisan, $moment);
    }

    public function isWithinWorkingHours(User $artisan, ?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        $hours = $artisan->relationLoaded('workingHours')
            ? $artisan->workingHours
            : $artisan->workingHours()->get();

        if ($hours->isEmpty()) {
            return true;
        }

        $day = ((int) $moment->format('N')) % 7;

        $slot = $hours->first(fn ($h) => (int) $h->day_of_week === $day);

        if (! $slot || ! $slot->is_working_day) {
            return false;
        }

        if ($slot->start_time === null || $slot->end_time === null) {
            return true;
        }

        $time = $moment->format('H:i:s');

        return $time >= $slot->start_time && $time <= $slot->end_time;
    }

    public function hasActiveUnavailability(User $artisan, ?\DateTimeInterface $moment = null): bool
    {
        $moment ??= now();

        $unavailabilities = $artisan->relationLoaded('unavailabilities')
            ? $artisan->unavailabilities
            : $artisan->unavailabilities()->get();

        return $unavailabilities->contains(fn ($unavailability) => $unavailability->isActiveAt($moment));
    }

    public function hasActiveIntervention(User $artisan): bool
    {
        if ($artisan->relationLoaded('acceptedRepairRequests')) {
            return $artisan->acceptedRepairRequests->contains(fn ($request) => in_array($request->status, [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS], true));
        }

        return $artisan->acceptedRepairRequests()
            ->whereIn('status', [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS])
            ->exists();
    }

    public function ensureCompatible(User $artisan, RepairRequest $repairRequest, string $field = 'artisan_id'): void
    {
        if ($artisan->role !== 'artisan') {
            throw ValidationException::withMessages([$field => ['Le dépanneur sélectionné est invalide.']]);
        }

        if (! $artisan->is_active) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur n’est pas actif.']]);
        }

        $profile = $artisan->artisanProfile;

        if (! $profile) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur n’a pas encore complété son profil.']]);
        }

        if (! $profile->is_available) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur n’est pas disponible.']]);
        }

        if ($profile->verification_status !== ArtisanProfile::VERIFICATION_VERIFIED) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur n’a pas encore été validé par Pannéo.']]);
        }

        $hasCategory = $artisan->relationLoaded('categories')
            ? $artisan->categories->contains('id', $repairRequest->category_id)
            : $artisan->categories()->whereKey($repairRequest->category_id)->exists();

        if (! $hasCategory) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur ne correspond pas au métier demandé.']]);
        }

        if (! $this->hasCompatibleZone($artisan, $repairRequest)) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur n’intervient pas dans la zone de la demande.']]);
        }

        if ($this->hasActiveIntervention($artisan)) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur est déjà en intervention.']]);
        }

        if ($this->hasActiveUnavailability($artisan)) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur est actuellement indisponible.']]);
        }

        if (! $this->isWithinWorkingHours($artisan)) {
            throw ValidationException::withMessages([$field => ['Ce dépanneur est hors de ses horaires de travail.']]);
        }
    }
}
