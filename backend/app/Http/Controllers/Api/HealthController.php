<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class HealthController extends Controller
{
    public function show()
    {
        return response()->json(['status' => 'ok']);
    }
}
