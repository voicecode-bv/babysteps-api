<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationPreference;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationPreferenceController extends Controller
{
    #[OA\Get(
        path: '/api/notification-preferences',
        summary: 'Get notification preferences',
        description: 'Return the authenticated user\'s push notification preferences.',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification preferences',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'post_liked', type: 'boolean'),
                            new OA\Property(property: 'post_commented', type: 'boolean'),
                            new OA\Property(property: 'comment_liked', type: 'boolean'),
                            new OA\Property(property: 'new_circle_post', type: 'boolean'),
                            new OA\Property(property: 'circle_invitation_accepted', type: 'boolean'),
                        ]),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $preferences = $request->user()->notification_preferences ?? NotificationPreference::defaults();

        return response()->json(['data' => $preferences]);
    }

    #[OA\Put(
        path: '/api/notification-preferences',
        summary: 'Update notification preferences',
        description: 'Update the authenticated user\'s push notification preferences.',
        tags: ['Notifications'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['post_liked', 'post_commented', 'comment_liked', 'new_circle_post', 'circle_invitation_accepted'],
                properties: [
                    new OA\Property(property: 'post_liked', type: 'boolean', example: true),
                    new OA\Property(property: 'post_commented', type: 'boolean', example: true),
                    new OA\Property(property: 'comment_liked', type: 'boolean', example: false),
                    new OA\Property(property: 'new_circle_post', type: 'boolean', example: true),
                    new OA\Property(property: 'circle_invitation_accepted', type: 'boolean', example: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Preferences updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $request->user()->update([
            'notification_preferences' => $request->validated(),
        ]);

        return response()->json(['data' => $request->user()->notification_preferences]);
    }
}
