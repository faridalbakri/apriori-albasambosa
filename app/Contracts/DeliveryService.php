<?php

namespace App\Contracts;

interface DeliveryService
{
    /**
     * Get list of available couriers.
     *
     * @return array{code: string, name: string, services: array}
     */
    public function getCouriers(): array;

    /**
     * Get shipping rates for a delivery.
     *
     * @param  array{postal_code: int|string}  $origin
     * @param  array{postal_code: int|string}  $destination
     * @param  array<int, array{name: string, description?: string, category?: string, value: int, quantity: int, weight: int, height?: int, length?: int, width?: int}>  $items
     * @param  string|null  $couriers  comma-separated courier codes, null = all available
     * @return array{pricing: array<int, array{courier_name: string, courier_code: string, courier_service_name: string, courier_service_code: string, price: float, duration: string}>}
     */
    public function getRates(array $origin, array $destination, array $items, ?string $couriers = null): array;

    /**
     * Create a delivery order with the courier.
     *
     * @param  array  $data  full shipment payload per Biteship API spec
     * @return array{id: string, courier: array, status: string, waybill_id: ?string, price: float}
     */
    public function createOrder(array $data): array;

    /**
     * Get an existing delivery order by Biteship order ID.
     */
    public function getOrder(string $orderId): array;

    /**
     * Cancel a delivery order.
     */
    public function cancelOrder(string $orderId, string $reason = ''): array;

    /**
     * Get tracking info by Biteship tracking ID.
     */
    public function getTracking(string $trackingId): array;

    /**
     * Get tracking info by waybill ID and courier code.
     */
    public function getPublicTracking(string $waybillId, string $courierCode): array;

    /**
     * Verify webhook signature from Biteship callback.
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool;
}
