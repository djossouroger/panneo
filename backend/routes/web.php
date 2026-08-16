<?php

use App\Http\Controllers\Admin\ArtisanController as AdminArtisanController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisputeController;
use App\Http\Controllers\Admin\RepairRequestController as AdminRepairRequestController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth:web', 'admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/repair-requests', [AdminRepairRequestController::class, 'index'])->name('repair-requests.index');
        Route::get('/repair-requests/{repairRequest}', [AdminRepairRequestController::class, 'show'])->name('repair-requests.show');
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::get('/artisans', [AdminArtisanController::class, 'index'])->name('artisans');
        Route::get('/artisans/{artisan}', [AdminArtisanController::class, 'show'])->name('artisans.show');

        Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications');
        Route::get('/verifications/{submission}', [VerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{submission}/approve', [VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{submission}/reject', [VerificationController::class, 'reject'])->name('verifications.reject');
        Route::post('/verifications/{submission}/reopen', [VerificationController::class, 'reopen'])->name('verifications.reopen');
        Route::get('/verifications/documents/{document}/download', [VerificationController::class, 'download'])->name('verifications.documents.download');
        Route::get('/verifications/documents/{document}/image', [VerificationController::class, 'image'])->name('verifications.documents.image');

        Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes');
        Route::get('/disputes/{dispute}', [DisputeController::class, 'show'])->name('disputes.show');
        Route::post('/disputes/{dispute}', [DisputeController::class, 'update'])->name('disputes.update');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
        Route::post('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews');
    });
});
