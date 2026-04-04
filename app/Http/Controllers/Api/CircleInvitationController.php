<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCircleInvitationRequest;
use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Notifications\CircleInvitationAcceptedNotification;
use App\Notifications\CircleInvitationNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CircleInvitationController extends Controller
{
    use AuthorizesRequests;

    #[OA\Post(
        path: '/api/circles/{circle}/invitations',
        summary: 'Invite to circle',
        description: 'Invite someone by email to join a circle. Sends an email notification to the invitee.',
        tags: ['Circle Invitations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'circle', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'friend@example.com'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Invitation sent',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invitation sent.'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 409, description: 'Already invited or already a member'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(StoreCircleInvitationRequest $request, Circle $circle): JsonResponse
    {
        $this->authorize('update', $circle);

        $email = $request->validated('email');

        abort_if(
            $circle->invitations()->where('email', $email)->whereNull('accepted_at')->exists(),
            409,
            'This email has already been invited.',
        );

        abort_if(
            $circle->members()->where('email', $email)->exists(),
            409,
            'This user is already a member.',
        );

        $invitation = $circle->invitations()->create([
            'invited_by' => $request->user()->id,
            'email' => $email,
            'token' => Str::random(64),
        ]);

        $invitation->load(['inviter', 'circle']);

        Notification::route('mail', $email)
            ->notify(new CircleInvitationNotification($invitation));

        return response()->json(['message' => 'Invitation sent.'], 201);
    }

    #[OA\Post(
        path: '/api/circle-invitations/{token}/accept',
        summary: 'Accept invitation',
        description: 'Accept a circle invitation using the token. Adds the authenticated user to the circle and notifies the inviter.',
        tags: ['Circle Invitations'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invitation accepted.'),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Invitation not found'),
            new OA\Response(response: 410, description: 'Invitation already accepted'),
        ],
    )]
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = CircleInvitation::where('token', $token)->firstOrFail();

        abort_if(! $invitation->isPending(), 410, 'Invitation already accepted.');

        $user = $request->user();

        $invitation->update(['accepted_at' => now()]);
        $invitation->circle->members()->syncWithoutDetaching([$user->id]);

        $invitation->inviter->notify(
            new CircleInvitationAcceptedNotification($invitation, $user->name),
        );

        return response()->json(['message' => 'Invitation accepted.']);
    }
}
