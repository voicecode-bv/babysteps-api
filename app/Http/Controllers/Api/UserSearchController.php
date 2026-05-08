<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SearchUserResource;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class UserSearchController extends Controller
{
    #[OA\Get(
        path: '/api/users/search',
        summary: 'Search users in shared circles',
        description: 'List users that share at least one circle with the authenticated user (either as circle owner or member). When `q` is provided, results are filtered against name, username, and email. The authenticated user is excluded from the results. Results are paginated.',
        tags: ['Users'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', minLength: 1, maxLength: 100)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of matching users',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SearchUser')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $authId = $request->user()->id;
        $term = trim((string) $request->string('q'));

        $circleIds = Circle::query()
            ->where(function ($query) use ($authId) {
                $query->where('user_id', $authId)
                    ->orWhereHas('members', fn ($q) => $q->whereKey($authId));
            })
            ->pluck('id');

        if ($circleIds->isEmpty()) {
            return SearchUserResource::collection(User::query()->whereRaw('1=0')->paginate(30));
        }

        $query = User::query()
            ->select(['id', 'name', 'username', 'avatar', 'avatar_thumbnail'])
            ->whereKeyNot($authId)
            ->where(function ($query) use ($circleIds) {
                $query->whereIn('id', Circle::query()
                    ->select('user_id')
                    ->whereIn('id', $circleIds))
                    ->orWhereIn('id', DB::table('circle_user')
                        ->select('user_id')
                        ->whereIn('circle_id', $circleIds));
            })
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';

                $q->where(function ($inner) use ($like) {
                    $inner->where('name', 'ILIKE', $like)
                        ->orWhere('username', 'ILIKE', $like)
                        ->orWhere('email', 'ILIKE', $like);
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        return SearchUserResource::collection($query->paginate(30));
    }
}
