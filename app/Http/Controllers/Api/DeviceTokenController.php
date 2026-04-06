<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        Log::info(json_encode($request->all()));
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update(['fcm_token' => $validated['token']]);

        return response()->json(null, 204);
    }
}
