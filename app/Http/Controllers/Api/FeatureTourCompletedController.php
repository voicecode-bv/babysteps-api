<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FeatureTourCompletedController extends Controller
{
    #[OA\Post(
        path: '/api/feature-tour/completed',
        summary: 'Mark the feature tour as completed for the authenticated user',
        description: 'Sets `feature_tour_completed_at` to the current time. Also sets `feature_tour_started_at` if it is still null (e.g. when the user completes via Replay without ever triggering the initial start).',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Feature tour marked as completed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $payload = ['feature_tour_completed_at' => now()];

        if ($user->feature_tour_started_at === null) {
            $payload['feature_tour_started_at'] = now();
        }

        $user->forceFill($payload)->save();

        return response()->noContent();
    }
}
