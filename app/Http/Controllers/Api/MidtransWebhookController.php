<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MidtransWebhookRequest;
use App\Jobs\ProcessMidtransWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    /**
     * Midtrans production IP addresses — 33 IPs (PRD §4.1.B, August 2026)
     */
    private const PRODUCTION_IPS = [
        '13.228.166.126', '52.220.80.5', '3.1.123.95', '8.215.30.222',
        '147.139.209.49', '8.215.32.142', '147.139.163.77', '8.215.25.24',
        '8.215.3.193', '147.139.210.20', '149.129.238.95', '8.215.9.206',
        '147.139.134.22', '149.129.253.222', '8.215.56.174', '8.215.27.65',
        '147.139.129.139', '149.129.192.10', '8.215.15.117', '149.129.234.6',
        '8.215.79.106', '149.129.192.204', '8.215.83.17', '147.139.197.147',
        '147.139.207.105', '147.139.193.191', '147.139.201.222', '8.215.82.175',
        '149.129.218.45', '8.215.10.140', '8.215.83.130', '147.139.206.209',
        '8.215.75.234',
    ];

    /**
     * Midtrans sandbox IP addresses for webhook IP whitelist.
     * See: PRD §4.1.B — 48 sandbox IPs (updated August 2026)
     */
    private const SANDBOX_IPS = [
        '34.142.147.133',
        '34.142.169.131',
        '34.142.231.22',
        '35.240.161.215',
        '34.142.227.232',
        '34.124.184.175',
        '35.197.130.2',
        '34.142.233.114',
        '8.215.26.211',
        '8.215.22.135',
        '8.215.93.92',
        '8.215.93.214',
        '8.215.93.76',
        '8.215.33.37',
        '8.215.26.148',
        '8.215.194.225',
        '8.215.12.199',
        '149.129.255.111',
        '149.129.216.115',
        '147.139.167.196',
        '147.139.179.47',
        '147.139.144.184',
        '147.139.169.196',
        '147.139.168.217',
        '8.215.17.96',
        '149.129.254.13',
        '147.139.203.227',
        '147.139.192.94',
        '147.139.206.250',
        '147.139.213.108',
        '8.215.23.167',
        '147.139.209.91',
        '8.215.21.228',
        '147.139.173.83',
        '147.139.132.215',
        '149.129.227.68',
        '149.129.234.77',
        '147.139.137.231',
        '147.139.180.156',
        '8.215.10.65',
        '8.215.22.163',
        '147.139.215.190',
        '8.215.0.89',
        '8.215.16.140',
        '147.139.165.251',
        '147.139.209.83',
        '147.139.167.157',
        '147.139.192.232',
    ];

    /**
     * Handle Midtrans payment notification webhook.
     * Returns 200 OK immediately, dispatches processing to queue.
     */
    public function __invoke(MidtransWebhookRequest $request): JsonResponse
    {
        // 1) IP whitelist check
        $this->verifyIpWhitelist($request);

        // 2) Validated payload (Form Request handles required field check)
        $payload = $request->json()->all();

        $orderNumber = $payload['order_id'];
        $transactionStatus = $payload['transaction_status'];
        $statusCode = $payload['status_code'];
        $grossAmount = $payload['gross_amount'];
        $signatureKey = $payload['signature_key'];

        // 3) Verify signature hash
        $this->verifySignature($payload);

        // 4) Return 200 OK immediately — processing happens async
        $fraudStatus = $payload['fraud_status'] ?? 'accept';

        // 5) Dispatch to queue for processing (idempotency handled inside job)
        ProcessMidtransWebhook::dispatch($orderNumber, $transactionStatus, $fraudStatus);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the request comes from a whitelisted Midtrans IP.
     * Checks sandbox IPs in sandbox mode, production IPs in production mode.
     */
    private function verifyIpWhitelist(Request $request): void
    {
        // test isolation — skip IP check when mock mode is on
        if (config('services.midtrans.mock')) {
            return;
        }

        $clientIp = $request->ip();
        $isProduction = config('services.midtrans.is_production');
        $whitelist = $isProduction ? self::PRODUCTION_IPS : self::SANDBOX_IPS;

        if (! in_array($clientIp, $whitelist, true)) {
            Log::warning('Midtrans webhook from non-whitelisted IP', [
                'ip' => $clientIp,
                'env' => $isProduction ? 'production' : 'sandbox',
            ]);

            abort(403, 'IP not whitelisted');
        }
    }

    /**
     * Verify the Midtrans signature hash.
     * SHA512(order_id + status_code + gross_amount + server_key)
     */
    private function verifySignature(array $payload): void
    {
        $serverKey = config('services.midtrans.server_key');

        $signatureKey = $payload['signature_key'] ?? '';
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';

        $computedSignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        if (! hash_equals($computedSignature, $signatureKey)) {
            Log::warning('Midtrans webhook signature mismatch', ['order_id' => $orderId]);

            abort(403, 'Invalid signature');
        }
    }
}
