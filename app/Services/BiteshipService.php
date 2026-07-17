<?php

namespace App\Services;

use App\Contracts\DeliveryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiteshipService implements DeliveryService
{
    private string $baseUrl;

    private string $apiKey;

    private bool $mock;

    public function __construct()
    {
        $this->baseUrl = config('services.biteship.base_url');
        $this->apiKey = config('services.biteship.api_key');
        $this->mock = config('services.biteship.mock');
    }

    public function getCouriers(): array
    {
        if ($this->mock) {
            return $this->mockCouriers();
        }

        $response = $this->get('/v1/couriers');

        // Real API wraps in {"success":true,"couriers":[...]}; unwrap to match contract
        return $response['couriers'] ?? $response;
    }

    public function getRates(array $origin, array $destination, array $items, ?string $couriers = null): array
    {
        if ($this->mock) {
            return $this->mockRates($couriers);
        }

        // Biteship requires couriers param; default to common Indonesian couriers when not specified
        $couriers = $couriers ?: 'jne,jnt,sicepat,gosend,grab';

        $payload = [
            'origin_postal_code' => (string) $origin['postal_code'],
            'destination_postal_code' => (string) $destination['postal_code'],
            'couriers' => $couriers,
            'items' => $items,
        ];

        return $this->post('/v1/rates/couriers', $payload);
    }

    public function createOrder(array $data): array
    {
        return $this->post('/v1/orders', $data);
    }

    public function getOrder(string $orderId): array
    {
        return $this->get("/v1/orders/{$orderId}");
    }

    public function cancelOrder(string $orderId, string $reason = ''): array
    {
        $payload = $reason ? ['reason' => $reason] : [];

        return $this->delete("/v1/orders/{$orderId}", $payload);
    }

    public function getTracking(string $trackingId): array
    {
        return $this->get("/v1/trackings/{$trackingId}");
    }

    public function getPublicTracking(string $waybillId, string $courierCode): array
    {
        return $this->post('/v1/trackings/public', [
            'waybill_id' => $waybillId,
            'courier_code' => $courierCode,
        ]);
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Biteship API key not configured — cannot verify webhook signature.');
        }

        // Biteship signs webhooks with HMAC-SHA256 using the API key as secret
        $computed = hash_hmac('sha256', $payload, $this->apiKey);

        return hash_equals($computed, $signature);
    }

    // ─── HTTP helpers ────────────────────────────────────────────────

    /**
     * @throws ConnectionException
     */
    private function get(string $path, array $query = []): array
    {
        return $this->request('get', $path, $query);
    }

    /**
     * @throws ConnectionException
     */
    private function post(string $path, array $data = []): array
    {
        return $this->request('post', $path, $data);
    }

    /**
     * @throws ConnectionException
     */
    private function delete(string $path, array $data = []): array
    {
        return $this->request('delete', $path, $data);
    }

    /**
     * @throws ConnectionException
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(15)
            ->connectTimeout(10)
            ->retry(3, 1000) // retry on transient failures with 1s backoff
            ->$method("{$this->baseUrl}{$path}", $method === 'get' ? $data : ($data ?: []));

        if ($response->failed()) {
            // extract only the error field, never log full body (may contain PII)
            $error = $response->json()['error'] ?? $response->body();
            $truncatedError = mb_substr($error, 0, 200);

            Log::error('Biteship API failed', [
                'method' => $method,
                'path' => $path,
                'status' => $response->status(),
                'error' => $truncatedError,
            ]);

            throw new \RuntimeException(
                "Biteship {$method} {$path} failed: HTTP {$response->status()}"
            );
        }

        return $response->json() ?: [];
    }

    // ─── Mock helpers for test isolation ─────────────────────────────

    private function mockCouriers(): array
    {
        // Format matches real Biteship API response (sandbox returns per-service entries)
        return [
            [
                'courier_code' => 'jne', 'courier_name' => 'JNE',
                'courier_service_code' => 'reg', 'courier_service_name' => 'REG',
                'tier' => 'standard', 'service_type' => 'regular',
                'shipment_duration_range' => '1 - 2', 'shipment_duration_unit' => 'days',
                'description' => 'JNE Reguler',
            ],
            [
                'courier_code' => 'jnt', 'courier_name' => 'J&T Express',
                'courier_service_code' => 'reg', 'courier_service_name' => 'REG',
                'tier' => 'standard', 'service_type' => 'regular',
                'shipment_duration_range' => '1 - 2', 'shipment_duration_unit' => 'days',
                'description' => 'J&T Reguler',
            ],
            [
                'courier_code' => 'sicepat', 'courier_name' => 'SiCepat',
                'courier_service_code' => 'reg', 'courier_service_name' => 'REG',
                'tier' => 'standard', 'service_type' => 'regular',
                'shipment_duration_range' => '1 - 2', 'shipment_duration_unit' => 'days',
                'description' => 'SiCepat Reguler',
            ],
            [
                'courier_code' => 'gosend', 'courier_name' => 'GoSend',
                'courier_service_code' => 'instant', 'courier_service_name' => 'Instant',
                'tier' => 'premium', 'service_type' => 'same_day',
                'shipment_duration_range' => '1 - 3', 'shipment_duration_unit' => 'hours',
                'description' => 'GoSend Instant',
            ],
            [
                'courier_code' => 'grab', 'courier_name' => 'GrabExpress',
                'courier_service_code' => 'instant', 'courier_service_name' => 'Instant',
                'tier' => 'premium', 'service_type' => 'same_day',
                'shipment_duration_range' => '1 - 3', 'shipment_duration_unit' => 'hours',
                'description' => 'GrabExpress Instant',
            ],
        ];
    }

    private function mockRates(?string $couriers): array
    {
        $allPricing = [
            ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'REG', 'courier_service_code' => 'reg', 'price' => 18_000, 'duration' => '1-2 hari'],
            ['courier_name' => 'J&T Express', 'courier_code' => 'jnt', 'courier_service_name' => 'REG', 'courier_service_code' => 'reg', 'price' => 16_000, 'duration' => '1-2 hari'],
            ['courier_name' => 'SiCepat', 'courier_code' => 'sicepat', 'courier_service_name' => 'REG', 'courier_service_code' => 'reg', 'price' => 15_000, 'duration' => '1-2 hari'],
            ['courier_name' => 'GoSend', 'courier_code' => 'gosend', 'courier_service_name' => 'Instant', 'courier_service_code' => 'instant', 'price' => 25_000, 'duration' => '± 2 jam'],
            ['courier_name' => 'GrabExpress', 'courier_code' => 'grab', 'courier_service_name' => 'Instant', 'courier_service_code' => 'instant', 'price' => 24_000, 'duration' => '± 2 jam'],
        ];

        $pricing = $couriers
            ? array_values(array_filter($allPricing, fn ($p) => in_array($p['courier_code'], explode(',', $couriers))))
            : $allPricing;

        return ['pricing' => $pricing];
    }
}
