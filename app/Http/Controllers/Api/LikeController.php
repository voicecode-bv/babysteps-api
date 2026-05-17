<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Notifications\PostLiked;
use App\Support\MediaUrl;
use App\Support\PostViewerVisibility;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

class LikeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Onder deze versie verwacht de client gewoon een lijst gebruikers met
     * `id/name/username/avatar` per like; een placeholder met `is_visible: false`
     * zou crashen op `like.user.name`. Vanaf 1.1.0 ondersteunt de client
     * placeholders en filteren we niet meer aan de query-kant.
     */
    private const VISIBILITY_V2_MIN_VERSION = '1.1.0';

    #[OA\Get(
        path: '/api/posts/{post}/likes',
        summary: 'List likes',
        description: 'Return a paginated list of users who liked the post, newest first. Likes by users outside the viewer\'s shared circles are returned as `{id, is_visible: false}` placeholders for clients on `X-App-Version` >= 1.1.0, and silently filtered out for older clients.',
        tags: ['Likes'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of users who liked the post',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'is_visible', type: 'boolean'),
                                new OA\Property(property: 'name', type: 'string', nullable: true),
                                new OA\Property(property: 'username', type: 'string', nullable: true),
                                new OA\Property(property: 'avatar', type: 'string', nullable: true),
                            ],
                        )),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Post not found'),
        ],
    )]
    public function index(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $viewer = $request->user();
        $visibility = PostViewerVisibility::for($viewer, $post);

        $clientVersion = (string) $request->header('X-App-Version', '0.0.0');
        $supportsVisibilityMarkers = version_compare(
            $clientVersion,
            self::VISIBILITY_V2_MIN_VERSION,
            '>='
        );

        $base = $post->likes()
            ->with('user:id,name,username,avatar')
            ->latest();

        // Oude clients (geen header of versie < drempel) krijgen geen
        // placeholders terug — hun LikeUser interface vereist een user. Privacy
        // blijft gewaarborgd door op query-niveau te filteren.
        if (! $supportsVisibilityMarkers) {
            $visibility->scopeLikesQuery($base);

            $likes = $base->paginate(50);

            return response()->json([
                'data' => $likes->getCollection()->map(fn ($like) => [
                    'id' => $like->user->id,
                    'is_visible' => true,
                    'name' => $like->user->name,
                    'username' => $like->user->username,
                    'avatar' => MediaUrl::sign($like->user->avatar),
                ])->values(),
                'meta' => $this->paginationMeta($likes),
            ]);
        }

        $likes = $base->paginate(50);

        $likerIds = $likes->getCollection()->pluck('user_id')->unique();
        $visibleSet = $visibility->visibleSubset($likerIds);

        return response()->json([
            'data' => $likes->getCollection()->map(function ($like) use ($visibleSet) {
                if (! $visibleSet->has($like->user_id)) {
                    return [
                        'id' => $like->user_id,
                        'is_visible' => false,
                    ];
                }

                return [
                    'id' => $like->user->id,
                    'is_visible' => true,
                    'name' => $like->user->name,
                    'username' => $like->user->username,
                    'avatar' => MediaUrl::sign($like->user->avatar),
                ];
            })->values(),
            'meta' => $this->paginationMeta($likes),
        ]);
    }

    /**
     * @return array{current_page: int, last_page: int, per_page: int, total: int}
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    #[OA\Post(
        path: '/api/posts/{post}/like',
        summary: 'Like post',
        description: 'Like a post. Idempotent — liking an already-liked post has no effect.',
        tags: ['Likes'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Post liked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'liked', type: 'boolean', example: true),
                        new OA\Property(property: 'likes_count', type: 'integer', example: 5),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Cannot like your own post'),
            new OA\Response(response: 404, description: 'Post not found'),
        ],
    )]
    public function store(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        abort_if($request->user()->id === $post->user_id, 403, 'Cannot like your own post.');

        $like = $post->likes()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $throttleKey = "notify:post-liked:{$post->id}:{$request->user()->id}";

        if ($like->wasRecentlyCreated && Cache::add($throttleKey, true, now()->addHour())) {
            $post->user->notify(new PostLiked($request->user(), $post));
        }

        return response()->json([
            'liked' => true,
            'likes_count' => $post->refresh()->likes_count,
        ], 201);
    }

    #[OA\Delete(
        path: '/api/posts/{post}/like',
        summary: 'Unlike post',
        description: 'Remove a like from a post.',
        tags: ['Likes'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'post', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Like removed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'liked', type: 'boolean', example: false),
                        new OA\Property(property: 'likes_count', type: 'integer', example: 4),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Post not found'),
        ],
    )]
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $like = $post->likes()
            ->where('user_id', $request->user()->id)
            ->first();

        $like?->delete();

        return response()->json([
            'liked' => false,
            'likes_count' => $post->refresh()->likes_count,
        ]);
    }
}
