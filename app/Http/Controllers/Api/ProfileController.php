<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Get(
        path: '/api/profiles/{username}',
        summary: 'Show profile',
        description: 'Return a user\'s public profile with post count.',
        tags: ['Profiles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User profile',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/Profile'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User not found'),
        ],
    )]
    public function show(User $user): ProfileResource
    {
        $user->loadCount('posts');

        return new ProfileResource($user);
    }

    #[OA\Get(
        path: '/api/profiles/{username}/posts',
        summary: 'List profile posts',
        description: 'Return a paginated list of posts by the given user.',
        tags: ['Profiles'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'username', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of user posts',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Post')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'User not found'),
        ],
    )]
    public function posts(User $user): AnonymousResourceCollection
    {
        $posts = $user->posts()
            ->with('user:id,name,username,avatar')
            ->withCount(['likes', 'comments'])
            ->latest()
            ->paginate(10);

        return PostResource::collection($posts);
    }

    #[OA\Put(
        path: '/api/profile',
        summary: 'Update profile',
        description: 'Update the authenticated user\'s profile details.',
        tags: ['Profiles'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                    new OA\Property(property: 'bio', type: 'string', nullable: true, example: 'Hello world'),
                    new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'https://example.com/avatar.jpg'),
                    new OA\Property(property: 'locale', type: 'string', example: 'en'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(UpdateProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
    }
}
