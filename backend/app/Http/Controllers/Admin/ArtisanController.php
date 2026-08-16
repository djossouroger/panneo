<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\RepairRequest;
use App\Models\RepairRequestOffer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArtisanController extends Controller
{
    public function index()
    {
        $artisans = User::where('role', 'artisan')
            ->with('artisanProfile.category')
            ->withCount([
                'repairRequestOffers as offers_count',
                'repairRequestOffers as accepted_offers_count' => fn ($query) => $query->where('status', RepairRequestOffer::STATUS_ACCEPTED),
                'reviewsReceived as reviews_count',
                'verificationSubmissions as pending_verifications' => fn ($query) => $query->where('status', \App\Models\ArtisanVerificationSubmission::STATUS_PENDING),
            ])
            ->withAvg('reviewsReceived as average_rating', 'rating')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.artisans', compact('artisans'));
    }

    public function show(User $artisan)
    {
        abort_unless($artisan->role === 'artisan', 404);

        $artisan->load('artisanProfile.category', 'verificationSubmissions.documents');
        $artisan->loadCount([
            'repairRequestOffers as offers_count',
            'repairRequestOffers as accepted_offers_count' => fn ($query) => $query->where('status', RepairRequestOffer::STATUS_ACCEPTED),
            'reviewsReceived as reviews_count',
            'acceptedRepairRequests as completed_interventions' => fn ($query) => $query->where('status', RepairRequest::STATUS_COMPLETED),
        ]);
        $artisan->loadAvg('reviewsReceived as average_rating', 'rating');

        $recentReviews = Review::where('artisan_id', $artisan->id)
            ->with(['client', 'repairRequest'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.artisan.show', compact('artisan', 'recentReviews'));
    }
}
