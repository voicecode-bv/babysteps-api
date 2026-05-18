<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Enums\MediaStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessFileFluxCallback;
use App\Models\PostMedia;
use Codingmonkeys\FileFlux\Webhook\SignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileFluxWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.fileflux.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::warning('FileFlux webhook hit but no secret configured');

            return new JsonResponse(['message' => 'Webhook not configured.'], 503);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-FileFlux-Signature');

        if (! SignatureVerifier::verify($rawBody, $signature, $secret)) {
            Log::warning('FileFlux webhook signature mismatch', [
                'has_header' => (bool) $signature,
                'body_length' => strlen($rawBody),
            ]);

            return new JsonResponse(['message' => 'Invalid signature.'], 401);
        }

        $payload = $request->json()->all();
        $taskId = $payload['task_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (! is_string($taskId) || $taskId === '' || ! is_string($status) || $status === '') {
            return new JsonResponse(['message' => 'Missing task_id or status.'], 422);
        }

        // Idempotency: als de PostMedia al Ready is, hoeven we niets meer te
        // doen. We retourneren wel 200 zodat FileFlux niet blijft retryen.
        $media = PostMedia::where('external_job_id', $taskId)->first();

        if ($media?->status === MediaStatus::Ready) {
            return new JsonResponse(['message' => 'Already processed.'], 200);
        }

        $outputs = is_array($payload['outputs'] ?? null) ? $payload['outputs'] : [];
        $errorMessage = is_array($payload['error'] ?? null)
            ? ($payload['error']['message'] ?? null)
            : ($payload['error'] ?? null);

        ProcessFileFluxCallback::dispatch(
            taskId: $taskId,
            status: $status,
            outputs: $outputs,
            errorMessage: is_string($errorMessage) ? $errorMessage : null,
        );

        return new JsonResponse(['message' => 'Accepted.'], 202);
    }
}
