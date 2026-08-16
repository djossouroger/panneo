<?php

namespace App\Http\Resources;

use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepairRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $acceptedArtisan = $this->acceptedArtisan;
        $acceptedProfile = $acceptedArtisan?->artisanProfile;
        $activeOffer = $this->activeOffer;
        $latestOffer = $this->latestOffer;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
                'icon' => $this->category?->icon,
            ],
            'title' => $this->title,
            'description' => $this->description,
            'images' => collect($this->images ?? [])->map(fn (string $path) => url('storage/'.$path))->values()->all(),
            'location' => [
                'city' => $this->city,
                'district' => $this->district,
                'address_details' => $this->address_details,
            ],
            'status' => $this->status,
            'current_offer' => $activeOffer ? $this->offerSummary($activeOffer) : null,
            'last_offer' => $latestOffer ? $this->offerSummary($latestOffer) : null,
            'offers' => $this->whenLoaded('offers', fn () => $this->offers->map(fn (RepairRequestOffer $offer) => $this->offerSummary($offer))->values()),
            'artisan' => $this->acceptedArtisanPayload($acceptedArtisan, $acceptedProfile),
            'client' => $this->when($this->relationLoaded('client'), fn () => $this->clientPayload()),
            'created_at' => $this->created_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'review' => $this->whenLoaded('review', fn () => $this->review ? [
                'id' => $this->review->id,
                'rating' => (int) $this->review->rating,
                'comment' => $this->review->comment,
                'client' => $this->whenLoaded('review.client', fn () => $this->review->client ? [
                    'id' => $this->review->client->id,
                    'name' => $this->review->client->name,
                ] : null),
                'created_at' => $this->review->created_at?->toISOString(),
            ] : null),
        ];
    }

    private function acceptedArtisanPayload($artisan, $profile): ?array
    {
        if (! $artisan || ! in_array($this->status, [RepairRequest::STATUS_ACCEPTED, RepairRequest::STATUS_IN_PROGRESS, RepairRequest::STATUS_COMPLETED], true)) {
            return null;
        }

        return [
            'id' => $artisan->id,
            'name' => $artisan->name,
            'category' => $profile?->category?->name ?? $this->category?->name,
            'city' => $profile?->city,
            'district' => $profile?->district,
            'phone' => $artisan->phone,
        ];
    }

    private function clientPayload(): ?array
    {
        if (! $this->client) {
            return null;
        }

        return [
            'id' => $this->client->id,
            'name' => $this->client->name,
            'phone' => $this->client->phone,
        ];
    }

    private function offerSummary(RepairRequestOffer $offer): array
    {
        $artisan = $offer->artisan;
        $profile = $artisan?->artisanProfile;

        return [
            'id' => $offer->id,
            'status' => $offer->status,
            'artisan' => $artisan ? [
                'id' => $artisan->id,
                'name' => $artisan->name,
                'category' => $profile?->category?->name,
                'city' => $profile?->city,
                'district' => $profile?->district,
            ] : null,
            'created_at' => $offer->created_at?->toISOString(),
            'responded_at' => $offer->responded_at?->toISOString(),
        ];
    }
}
