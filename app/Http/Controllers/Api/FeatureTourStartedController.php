<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FeatureTourStartedController extends Controller
{
    #[OA\Post(
        path: '/api/feature-tour/started',
        summary: 'Mark the feature tour as started for the authenticated user',
        description: 'Sets `feature_tour_started_at` to the current time if it is null. Idempotent: subsequent calls leave the original timestamp untouched.',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Feature tour marked as started'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->feature_tour_started_at === null) {
            $user->forceFill(['feature_tour_started_at' => now()])->save();
        }

        return response()->noContent();
    }
}
