<?php

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SubscriptionController extends Controller
{
    #[OA\Get(
        path: '/api/subscription/me',
        summary: 'Current subscription',
        description: "Returns the authenticated user's current plan, whether they're on a paid plan, and the active subscription if one exists. Clients should use this — not the device's StoreKit/Billing entitlements — as the source of truth for premium status.",
        tags: ['Subscriptions'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current subscription state',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'plan', type: 'object'),
                        new OA\Property(property: 'is_paid', type: 'boolean'),
                        new OA\Property(
                            property: 'subscription',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                new OA\Property(property: 'channel', type: 'string', example: 'apple', nullable: true),
                                new OA\Property(property: 'status', type: 'string', example: 'active', nullable: true),
                                new OA\Property(property: 'auto_renew', type: 'boolean'),
                                new OA\Property(property: 'current_period_end', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'renews_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'trial_ends_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'grace_ends_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'canceled_at', type: 'string', format: 'date-time', nullable: true),
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('activeSubscription.plan', 'activeSubscription.price');

        $plan = $user->currentPlan();
        $plan->loadMissing('prices');

        $subscription = $user->activeSubscription;

        return new JsonResponse([
            'plan' => (new PlanResource($plan))->toArray($request),
            'is_paid' => $user->isOnPaidPlan(),
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'channel' => $subscription->channel?->value,
                'status' => $subscription->status?->value,
                'auto_renew' => $subscription->auto_renew,
                'current_period_end' => $subscription->current_period_end,
                'renews_at' => $subscription->renews_at,
                'trial_ends_at' => $subscription->trial_ends_at,
                'grace_ends_at' => $subscription->grace_ends_at,
                'canceled_at' => $subscription->canceled_at,
            ] : null,
        ]);
    }
}
