<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Enums\SubscriptionChannel;
use App\Http\Controllers\Controller;
use App\Jobs\Subscriptions\ProcessSubscriptionEvent;
use App\Models\SubscriptionEvent;
use App\Services\Subscriptions\ChannelRegistry;
use App\Services\Subscriptions\Channels\GoogleChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleWebhookController extends Controller
{
    public function __invoke(Request $request, ChannelRegistry $registry): JsonResponse
    {
        $log = Log::channel('subscriptions');

        /** @var GoogleChannel $channel */
        $channel = $registry->for(SubscriptionChannel::Google);

        $log->info('google webhook: received', [
            'has_bearer' => $request->bearerToken() !== null,
            'message_id' => $request->input('message.messageId'),
            'subscription' => $request->input('subscription'),
        ]);

        try {
            $outcome = $channel->handleWebhook($request);
        } catch (\Throwable $e) {
            $log->warning('google webhook: handleWebhook failed', [
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
                'message_id' => $request->input('message.messageId'),
            ]);

            return new JsonResponse(['message' => 'Invalid Pub/Sub payload.', 'error' => $e->getMessage()], 400);
        }

        if ($outcome->externalEventId === '') {
            $log->warning('google webhook: missing message id in outcome');

            return new JsonResponse(['message' => 'Missing message id.'], 422);
        }

        $existing = SubscriptionEvent::query()
            ->where('channel', SubscriptionChannel::Google)
            ->where('external_event_id', $outcome->externalEventId)
            ->first();

        if ($existing) {
            $log->info('google webhook: duplicate notification ignored', [
                'message_id' => $outcome->externalEventId,
            ]);

            return new JsonResponse(['message' => 'Already processed.'], 200);
        }

        $event = SubscriptionEvent::query()->create([
            'channel' => SubscriptionChannel::Google,
            'type' => $outcome->type,
            'external_event_id' => $outcome->externalEventId,
            'occurred_at' => $outcome->occurredAt,
            'received_at' => now(),
            'payload' => $outcome->payload + ['channel_subscription_id' => $outcome->channelSubscriptionId],
        ]);

        $log->info('google webhook: accepted', [
            'event_id' => $event->id,
            'message_id' => $outcome->externalEventId,
            'type' => $outcome->type?->value,
            'channel_subscription_id' => $outcome->channelSubscriptionId,
        ]);

        ProcessSubscriptionEvent::dispatch($event->id);

        return new JsonResponse(['message' => 'Accepted.'], 202);
    }
}
