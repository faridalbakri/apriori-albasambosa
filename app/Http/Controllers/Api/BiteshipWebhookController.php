<?php

namespace App\Http\Controllers\Api;

use App\Contracts\DeliveryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\BiteshipWebhookRequest;
use App\Jobs\ProcessBiteshipWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BiteshipWebhookController extends Controller
{
    /**
     * Handle Biteship delivery status webhook.
     * Returns 200 OK immediately, dispatches processing to queue.
     *
     * Verification steps (per Biteship docs):
     * 1. Verify HMAC-SHA256 signature header
     * 2. IP whitelist check
     * 3. Return 200 OK → dispatch async job
     */
    public function __invoke(BiteshipWebhookRequest $request): JsonResponse
    {
        $payload = $request->json()->all();
        $rawBody = $request->getContent();

        // 1) Verify webhook signature (delegated to service for single source of truth)
        $this->verifySignature($rawBody, $request->header('X-Biteship-Signature'));

        // 2) IP whitelist check
        $this->verifyIpWhitelist($request);

        // 3) Return 200 OK immediately — processing happens async
        ProcessBiteshipWebhook::dispatch(
            event: $payload['event'],
            waybillId: $payload['waybill_id'],
            status: $payload['status'],
            courierCompany: $payload['courier']['company'],
            courierWaybillId: $payload['courier']['waybill_id'],
            biteshipOrderId: $payload['order_id'],
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the Biteship webhook signature via the DeliveryService.
     */
    private function verifySignature(string $rawBody, ?string $signature): void
    {
        if (! $signature) {
            Log::warning('Biteship webhook: missing signature header');

            abort(401, 'Missing signature');
        }

        /** @var DeliveryService $biteship */
        $biteship = app(DeliveryService::class);

        if (! $biteship->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Biteship webhook: signature mismatch');

            abort(403, 'Invalid signature');
        }
    }

    /**
     * Verify the request IP is whitelisted.
     * Reads whitelist from config (not env directly — works with config:cache).
     */
    private function verifyIpWhitelist(BiteshipWebhookRequest $request): void
    {
        // test isolation — skip IP check when mock mode is on
        if (config('services.biteship.mock')) {
            Log::warning('Biteship webhook: IP whitelist bypassed — mock mode is active. Ensure mock is disabled in production.');

            return;
        }

        $whitelistRaw = config('services.biteship.webhook_ips', '');

        if (empty($whitelistRaw)) {
            Log::warning('Biteship webhook: no IP whitelist configured — accepting all IPs');

            return;
        }

        $whitelist = array_map('trim', explode(',', $whitelistRaw));
        $clientIp = $request->ip();

        if (! in_array($clientIp, $whitelist, true)) {
            Log::warning('Biteship webhook from non-whitelisted IP', [
                'ip' => $clientIp,
            ]);

            abort(403, 'IP not whitelisted');
        }
    }
}
