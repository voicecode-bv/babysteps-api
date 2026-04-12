<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceInfoRequest;
use Illuminate\Http\JsonResponse;

class DeviceInfoController extends Controller
{
    public function store(StoreDeviceInfoRequest $request): JsonResponse
    {
        $request->user()->update([
            'device_info' => $request->validated(),
        ]);

        return response()->json(null, 204);
    }
}
