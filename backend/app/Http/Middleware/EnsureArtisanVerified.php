<?php

namespace App\Http\Middleware;

use App\Models\ArtisanProfile;
use Closure;
use Illuminate\Http\Request;

class EnsureArtisanVerified
{
    public function handle(Request $request, Closure $next)
    {
        $profile = $request->user()?->artisanProfile;

        if (! $profile || $profile->verification_status !== ArtisanProfile::VERIFICATION_VERIFIED) {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte artisan doit être validé avant de recevoir des demandes.',
                'code' => 'ARTISAN_NOT_VERIFIED',
                'errors' => [
                    'verification_status' => ['Votre compte artisan n’est pas encore validé.'],
                ],
            ], 403);
        }

        return $next($request);
    }
}
