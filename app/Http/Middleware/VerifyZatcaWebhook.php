<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Verify that a webhook came from the compliance platform, and was not replayed.
 *
 * The platform signs the raw body, sha256=HMAC(body, secret), and the body
 * carries the delivery's own id and timestamp - so both are covered by the
 * signature. The X-Webhook-Timestamp header is not: it is unsigned, and the
 * platform sends it as ISO-8601 where this used to demand epoch seconds, which
 * refused every real delivery before its signature was read.
 *
 * Freshness is read from the signed timestamp, so a captured request cannot be
 * sent again later with a current header. Inside the window a replay is caught
 * by its id: the platform mints a new id for every attempt, retries included,
 * so an id seen twice is never a genuine delivery. A repeat is acknowledged
 * rather than refused, because the platform disables a subscription after ten
 * failed deliveries.
 */
class VerifyZatcaWebhook
{
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('zatca-integration.webhook_secret', '');

        if ($secret === '') {
            Log::critical('ZATCA webhook secret is not configured', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Webhook endpoint not configured.'], 503);
        }

        $signature = (string) $request->header('X-Webhook-Signature', '');

        if ($signature === '') {
            return $this->missingSignature();
        }

        $rawBody = $request->getContent();

        if (! hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $signature)) {
            return $this->refused('Webhook signature verification failed');
        }

        // Only a verified body is trusted to name a delivery, so nothing
        // unsigned ever reaches the cache.
        $body = json_decode($rawBody, true);
        $id = is_array($body) ? ($body['id'] ?? null) : null;

        if (! is_string($id) || $id === '' || ! $this->isFresh($body['timestamp'] ?? null)) {
            return $this->refused('Webhook timestamp is stale or invalid');
        }

        // A timestamp may sit up to the tolerance in the future and still pass,
        // so the id is remembered for twice the tolerance.
        if (! Cache::add('zatca-webhook:'.$id, true, self::TOLERANCE_SECONDS * 2)) {
            Log::warning('ZATCA webhook: repeated delivery ignored', [
                'delivery_id' => $id,
            ]);

            return response()->json(['success' => true, 'message' => 'Already processed'], 200);
        }

        return $next($request);
    }

    private function isFresh(mixed $timestamp): bool
    {
        if (! is_string($timestamp) || $timestamp === '') {
            return false;
        }

        try {
            $sent = Carbon::parse($timestamp);
        } catch (Throwable) {
            return false;
        }

        return abs(now()->getTimestamp() - $sent->getTimestamp()) <= self::TOLERANCE_SECONDS;
    }

    private function missingSignature(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'MISSING_WEBHOOK_SIGNATURE',
                'message' => 'Required webhook header X-Webhook-Signature is missing',
            ],
        ], 400);
    }

    private function refused(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INVALID_WEBHOOK_SIGNATURE',
                'message' => $message,
            ],
        ], 401);
    }
}
