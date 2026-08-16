<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

class RepairRequestController extends Controller
{
    public function index(Request $request)
    {
        $allowedStatuses = [
            RepairRequest::STATUS_PENDING,
            RepairRequest::STATUS_AWAITING_ARTISAN,
            RepairRequest::STATUS_ACCEPTED,
            RepairRequest::STATUS_IN_PROGRESS,
            RepairRequest::STATUS_COMPLETED,
            RepairRequest::STATUS_CANCELLED,
        ];

        $query = RepairRequest::with([
            'client',
            'category',
            'acceptedArtisan.artisanProfile.category',
            'activeOffer.artisan',
        ])->latest();

        if (in_array($request->query('status'), $allowedStatuses, true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        return view('admin.repair-requests.index', [
            'repairRequests' => $query->get(),
            'categories' => Category::orderBy('name')->get(),
            'selectedStatus' => $request->query('status', 'all'),
            'selectedCategoryId' => $request->query('category_id'),
        ]);
    }

    public function show(RepairRequest $repairRequest)
    {
        return view('admin.repair-requests.show', [
            'repairRequest' => $repairRequest->load([
                'client',
                'category',
                'acceptedArtisan.artisanProfile.category',
                'activeOffer.artisan',
                'offers.artisan',
            ]),
        ]);
    }
}
