<?php

namespace App\Http\Controllers\Api;

use App\Enums\FeatureTourSegment;
use App\Http\Controllers\Controller;
use App\Models\FeatureTourStep;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FeatureTourSegmentController extends Controller
{
    #[OA\Post(
        path: '/api/feature-tour/segments/{step}/completed',
        summary: 'Mark a feature tour segment as completed',
        description: 'Records that the authenticated user completed the given tour segment. Idempotent: re-posting the same segment keeps the original `completed_at` timestamp.',
        tags: ['Account'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'step',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['feed', 'circles', 'circle-detail', 'persons', 'default-circles', 'give', 'map', 'profile']),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Segment recorded'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Invalid segment'),
        ],
    )]
    public function __invoke(Request $request, FeatureTourSegment $step): Response
    {
        FeatureTourStep::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'step' => $step,
            ],
            ['completed_at' => now()],
        );

        return response()->noContent();
    }
}
