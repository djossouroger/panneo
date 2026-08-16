<?php

namespace App\Http\Resources;

use App\Models\RepairRequestOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RepairRequestOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $repairRequest = $this->repairRequest;
        $category = $repairRequest?->category;
        $client = $repairRequest?->client;
        $artisanProfile = $this->artisan?->artisanProfile;
        $isAccepted = $this->status === RepairRequestOffer::STATUS_ACCEPTED;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'artisan' => $this->artisan ? [
                'id' => $this->artisan->id,
                'name' => $this->artisan->name,
                'category' => $artisanProfile?->category?->name,
                'city' => $artisanProfile?->city,
                'district' => $artisanProfile?->district,
                'is_available' => (bool) $artisanProfile?->is_available,
            ] : null,
            'request' => $repairRequest ? [
                'id' => $repairRequest->id,
                'reference' => $repairRequest->reference,
                'status' => $repairRequest->status,
                'category' => [
                    'id' => $category?->id,
                    'name' => $category?->name,
                    'slug' => $category?->slug,
                    'icon' => $category?->icon,
                ],
                'title' => $repairRequest->title,
                'description' => $repairRequest->description,
                'images' => collect($repairRequest->images ?? [])->map(fn (string $path) => url('storage/'.$path))->values()->all(),
                'location' => [
                    'city' => $repairRequest->city,
                    'district' => $repairRequest->district,
                    'address_details' => $isAccepted ? $repairRequest->address_details : null,
                ],
                'client' => $isAccepted && $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                    'phone' => $client->phone,
                ] : null,
                'created_at' => $repairRequest->created_at?->toISOString(),
                'accepted_at' => $repairRequest->accepted_at?->toISOString(),
                'started_at' => $repairRequest->started_at?->toISOString(),
                'completed_at' => $repairRequest->completed_at?->toISOString(),
            ] : null,
        ];
    }
}
