<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::with([
            'client',
            'artisan',
            'repairRequest.category',
        ])->latest()->get();

        return view('admin.reviews', compact('reviews'));
    }
}
