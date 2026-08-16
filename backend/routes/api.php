<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ArtisanController;
use App\Http\Controllers\Api\ArtisanOfferController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PhoneAuthController;
use App\Http\Controllers\Api\RepairRequestController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('/password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
        Route::post('/email-verify/send', [AuthController::class, 'sendEmailVerification'])->middleware('throttle:3,15');
        Route::post('/email-verify/confirm', [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1');
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('/me', [AuthController::class, 'me'])->middleware(['auth:sanctum', 'active']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/health', [HealthController::class, 'show']);

    Route::get('/artisans/{artisan}', [ArtisanController::class, 'publicProfile']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        Route::prefix('auth/phone')->middleware('throttle:3,15')->group(function () {
            Route::post('/send-code', [PhoneAuthController::class, 'sendCode']);
            Route::post('/resend', [PhoneAuthController::class, 'resend']);
        });
        Route::post('/auth/phone/verify', [PhoneAuthController::class, 'verify']);

        Route::prefix('account')->group(function () {
            Route::put('/profile', [AccountController::class, 'updateProfile']);
            Route::post('/profile-photo', [AccountController::class, 'uploadProfilePhoto']);
            Route::post('/email/send-code', [AccountController::class, 'requestEmailChange'])->middleware('throttle:3,15');
            Route::post('/email', [AccountController::class, 'changeEmail']);
            Route::post('/phone/send-code', [AccountController::class, 'requestPhoneChange'])->middleware('throttle:3,15');
            Route::post('/phone', [AccountController::class, 'changePhone']);
            Route::get('/sessions', [AccountController::class, 'sessions']);
            Route::delete('/sessions/{session}', [AccountController::class, 'revokeSession']);
            Route::post('/sessions/others', [AccountController::class, 'revokeOtherSessions']);
            Route::post('/delete', [AccountController::class, 'deleteAccount']);
        });

        Route::get('/disputes', [DisputeController::class, 'index']);
        Route::get('/disputes/{dispute}', [DisputeController::class, 'show']);
        Route::post('/repair-requests/{repairRequest}/disputes', [DisputeController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'active', 'role:client'])->group(function () {
        Route::get('/repair-requests', [RepairRequestController::class, 'index']);
        Route::post('/repair-requests', [RepairRequestController::class, 'store']);
        Route::get('/repair-requests/{repairRequest}', [RepairRequestController::class, 'show']);
        Route::get('/repair-requests/{repairRequest}/available-artisans', [RepairRequestController::class, 'availableArtisans']);
        Route::post('/repair-requests/{repairRequest}/offers', [RepairRequestController::class, 'storeOffer']);
        Route::post('/repair-requests/{repairRequest}/review', [ReviewController::class, 'store']);
        Route::get('/repair-requests/{repairRequest}/review', [ReviewController::class, 'showByRepairRequest']);
        Route::patch('/repair-requests/{repairRequest}/cancel', [RepairRequestController::class, 'cancel']);

        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/artisans/{artisan}/favorite', [FavoriteController::class, 'toggle']);
        Route::get('/artisans/{artisan}/favorite', [FavoriteController::class, 'status']);
    });

    Route::middleware(['auth:sanctum', 'active', 'role:artisan'])->group(function () {
        Route::get('/artisan/profile', [ArtisanController::class, 'profile']);
        Route::put('/artisan/profile', [ArtisanController::class, 'updateProfile']);
        Route::patch('/artisan/availability', [ArtisanController::class, 'updateAvailability'])->middleware('artisan.verified');
        Route::post('/artisan/profile-photo', [ArtisanController::class, 'uploadProfilePhoto']);

        Route::put('/artisan/categories', [ArtisanController::class, 'updateCategories']);
        Route::put('/artisan/service-areas', [ArtisanController::class, 'updateServiceAreas']);
        Route::put('/artisan/working-hours', [ArtisanController::class, 'updateWorkingHours']);

        Route::get('/artisan/unavailabilities', [ArtisanController::class, 'unavailabilities']);
        Route::post('/artisan/unavailabilities', [ArtisanController::class, 'storeUnavailability']);
        Route::delete('/artisan/unavailabilities/{unavailability}', [ArtisanController::class, 'cancelUnavailability']);

        Route::get('/artisan/portfolio', [ArtisanController::class, 'portfolio']);
        Route::post('/artisan/portfolio', [ArtisanController::class, 'storePortfolioItem']);
        Route::delete('/artisan/portfolio/{item}', [ArtisanController::class, 'deletePortfolioItem']);

        Route::get('/artisan/verification', [ArtisanController::class, 'verification']);
        Route::post('/artisan/verification', [ArtisanController::class, 'submitVerification']);
        Route::post('/artisan/verification/cancel', [ArtisanController::class, 'cancelVerificationSubmission']);
        Route::get('/artisan/verification/documents/{document}', [ArtisanController::class, 'downloadVerificationDocument']);

        Route::middleware('artisan.verified')->group(function () {
            Route::get('/artisan/offers', [ArtisanOfferController::class, 'index']);
            Route::get('/artisan/offers/{offer}', [ArtisanOfferController::class, 'show']);
            Route::post('/artisan/offers/{offer}/accept', [ArtisanOfferController::class, 'accept']);
            Route::post('/artisan/offers/{offer}/reject', [ArtisanOfferController::class, 'reject']);

            Route::get('/artisan/repair-requests', [ArtisanController::class, 'repairRequests']);
            Route::get('/artisan/repair-requests/{repairRequest}', [ArtisanController::class, 'showRepairRequest']);
            Route::post('/artisan/repair-requests/{repairRequest}/start', [ArtisanController::class, 'startRepairRequest']);
            Route::post('/artisan/repair-requests/{repairRequest}/complete', [ArtisanController::class, 'completeRepairRequest']);
        });
    });
});
