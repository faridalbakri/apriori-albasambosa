<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    /**
     * Initialize Midtrans SDK config from Laravel config.
     * Called before every SDK call — idempotent, no side effects.
     */
    private static function configure(): void
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$clientKey = config('services.midtrans.client_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.is_sanitized');
        Config::$is3ds = config('services.midtrans.is_3ds');
        // custom curl options trigger SDK bug — accessing
        // CURLOPT_HTTPHEADER (10023) on a sparse array raises warning,
        // which Laravel converts to ErrorException. Let SDK defaults ride.
        // Config::$curlOptions = [CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 10];
    }

    /**
     * Generate a Snap token for an order's payment.
     * Token is passed to the frontend — server key NEVER leaves the backend.
     */
    public static function createSnapToken(Order $order): string
    {
        // reuse existing token — Midtrans rejects duplicate order_id
        if ($order->snap_token) {
            return $order->snap_token;
        }

        // test isolation — return deterministic mock token
        if (config('services.midtrans.mock')) {
            $token = 'mock-snap-token-'.$order->id;
            $order->update(['snap_token' => $token]);

            return $token;
        }

        self::configure();

        // eager load to avoid N+1 inside map closure
        $order->loadMissing('items.product');

        $items = $order->items->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'name' => $item->product->name,
            'price' => (int) $item->price,
            'quantity' => $item->quantity,
        ])->toArray();

        // Shipping as a separate line item so customer sees breakdown
        if ($order->shipping_cost > 0) {
            $items[] = [
                'id' => 'SHIPPING',
                'name' => 'Ongkos Kirim',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $order->order_number,
                'gross_amount' => (int) $order->total_price,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => $order->recipient_name ?? 'Customer',
                'phone' => $order->phone ?? '',
            ],
            'expiry' => [
                'unit' => 'hour',
                'duration' => 1,
            ],
        ];

        try {
            $token = Snap::getSnapToken($params);
            $order->update(['snap_token' => $token]);

            return $token;
        } catch (\Exception $e) {
            Log::error('Midtrans createSnapToken failed', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Secondary verification: fetch transaction status from Midtrans.
     * Returns null on failure so callers can handle gracefully.
     */
    public static function getOrderStatus(string $orderNumber): ?object
    {
        // test isolation — return mock based on order number pattern
        if (config('services.midtrans.mock')) {
            return self::mockOrderStatus($orderNumber);
        }

        self::configure();

        try {
            return Transaction::status($orderNumber);
        } catch (\Exception $e) {
            Log::error('Midtrans getOrderStatus failed', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Return mock transaction status for test isolation.
     * Returns null to trigger fallback-to-payload path in ProcessMidtransWebhook,
     * so tests control status via webhook payload, not mock data.
     */
    private static function mockOrderStatus(string $orderNumber): ?object
    {
        // Return null → fallback to webhook payload's transaction_status
        return null;
    }
}
