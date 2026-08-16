<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArtisanProfile;
use App\Models\ArtisanVerificationSubmission;
use App\Models\Dispute;
use App\Models\RepairRequest;
use App\Models\Review;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'requests_searching' => RepairRequest::where('status', RepairRequest::STATUS_PENDING)->count(),
            'requests_awaiting_artisan' => RepairRequest::where('status', RepairRequest::STATUS_AWAITING_ARTISAN)->count(),
            'requests_in_progress' => RepairRequest::where('status', RepairRequest::STATUS_IN_PROGRESS)->count(),
            'requests_completed' => RepairRequest::where('status', RepairRequest::STATUS_COMPLETED)->count(),
            'available_artisans' => ArtisanProfile::where('is_available', true)->count(),
            'reviews_count' => Review::count(),
            'pending_verifications' => ArtisanVerificationSubmission::where('status', ArtisanVerificationSubmission::STATUS_PENDING)->count(),
            'open_disputes' => Dispute::whereIn('status', [Dispute::STATUS_OPEN, Dispute::STATUS_IN_REVIEW])->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
