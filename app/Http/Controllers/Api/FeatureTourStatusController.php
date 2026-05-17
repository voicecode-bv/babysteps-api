<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FeatureTourStatusController extends Controller
{
    #[OA\Get(
        path: '/api/feature-tour/status',
        summary: 'Get the feature tour status for the authenticated user',
        description: 'Returns the `started_at` and `completed_at` timestamps plus the list of completed segment names. Clients use this to decide whether to auto-start the tour for a returning user.',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Feature tour status',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'segments', type: 'array', items: new OA\Items(type: 'string')),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'started_at' => $user->feature_tour_started_at?->toIso8601String(),
            'completed_at' => $user->feature_tour_completed_at?->toIso8601String(),
            'segments' => $user->featureTourSteps()->pluck('step')->all(),
        ]);
    }
}
