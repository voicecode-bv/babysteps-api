<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCircleMemberRequest;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class CircleMemberController extends Controller
{
    use AuthorizesRequests;

    #[OA\Post(
        path: '/api/circles/{circle}/members',
        summary: 'Add member',
        description: 'Add a user to a circle by username. Requires circle ownership.',
        tags: ['Circle Members'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'circle', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Member added',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Member added.'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreCircleMemberRequest $request, Circle $circle): JsonResponse
    {
        $this->authorize('update', $circle);

        $user = User::where('username', $request->validated('username'))->first();

        $circle->members()->syncWithoutDetaching([$user->id]);

        return response()->json(['message' => 'Member added.'], 201);
    }

    #[OA\Delete(
        path: '/api/circles/{circle}/members/{user}',
        summary: 'Remove member',
        description: 'Remove a user from a circle. Requires circle ownership.',
        tags: ['Circle Members'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'circle', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Member removed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Circle or user not found'),
        ],
    )]
    public function destroy(Circle $circle, User $user): JsonResponse
    {
        $this->authorize('update', $circle);

        $circle->members()->detach($user->id);

        return response()->json(null, 204);
    }
}
