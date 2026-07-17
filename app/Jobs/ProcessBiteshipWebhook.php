<?php

namespace App\Jobs;

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessBiteshipWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Unique lock duration (seconds). Prevents duplicate processing
     * of the same webhook within this window.
     */
    public int $uniqueFor = 300;

    /**
     * Unique key for job deduplication.
     */
    public function uniqueId(): string
    {
        return "biteship-webhook:{$this->courierWaybillId}:{$this->status}";
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $event,
        public string $waybillId,
        public string $status,
        public string $courierCompany,
        public string $courierWaybillId,
        public string $biteshipOrderId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TransitionOrderStatus $transitionOrderStatus): void
    {
        // Find shipment by courier waybill_id, Biteship order_id, or Biteship waybill_id
        $shipment = Shipment::where('waybill_id', $this->courierWaybillId)
            ->orWhere('waybill_id', $this->biteshipOrderId)
            ->orWhere('waybill_id', $this->waybillId)
            ->first();

        if (! $shipment) {
            Log::warning('Biteship webhook: shipment not found', [
                'waybill_id' => $this->courierWaybillId,
                'biteship_order_id' => $this->biteshipOrderId,
            ]);

            return;
        }

        $order = $shipment->order()->first();

        if (! $order) {
            Log::warning('Biteship webhook: order not found for shipment', [
                'shipment_id' => $shipment->id,
            ]);

            return;
        }

        // Idempotency: atomic check-and-update to prevent TOCTOU race
        $affected = Shipment::where('id', $shipment->id)
            ->where('tracking_status', '!=', $this->status)
            ->update(['tracking_status' => $this->status]);

        if ($affected === 0) {
            Log::debug('Biteship webhook: status unchanged or already updated (idempotent)', [
                'waybill_id' => $this->courierWaybillId,
                'status' => $this->status,
            ]);

            return;
        }

        Log::info('Biteship webhook: tracking status updated', [
            'shipment_id' => $shipment->id,
            'waybill_id' => $this->courierWaybillId,
            'new_status' => $this->status,
        ]);

        // Refresh model state after atomic update
        $shipment->refresh();
        $order->refresh();

        // Skip order-level transitions for terminal orders
        if (in_array($order->status, [OrderStatus::Cancel, OrderStatus::Completed, OrderStatus::RefundDone, OrderStatus::Expire], true)) {
            Log::info('Biteship webhook: order in terminal state, tracking-only update', [
                'order_id' => $order->id,
                'order_status' => $order->status->value,
            ]);

            return;
        }

        // Map Biteship status → order status transition
        $targetStatus = $this->mapToOrderStatus($this->status);

        if ($targetStatus && $order->status !== $targetStatus) {
            try {
                $transitionOrderStatus($order, $targetStatus);

                Log::info('Biteship webhook: order status transitioned', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'new_status' => $targetStatus->value,
                ]);

            } catch (InvalidStatusTransitionException $e) {
                Log::warning('Biteship webhook: invalid order transition', [
                    'order_id' => $order->id,
                    'current_status' => $order->status->value,
                    'target_status' => $targetStatus->value,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($this->status === 'failed') {
            $this->logFailedDelivery($order);
        }
    }

    /**
     * Map Biteship delivery status to our OrderStatus enum.
     */
    private function mapToOrderStatus(string $biteshipStatus): ?OrderStatus
    {
        return match ($biteshipStatus) {
            'picked_up' => OrderStatus::Shipping,
            'delivered' => OrderStatus::Delivered,
            default => null, // confirmed, in_transit, failed — no order transition
        };
    }

    /**
     * Log a failed delivery for admin attention.
     */
    private function logFailedDelivery(Order $order): void
    {
        $log = NotificationLog::create([
            'user_id' => $order->user_id,
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'channel' => 'biteship',
                'waybill_id' => $this->courierWaybillId,
                'status' => $this->status,
                'error' => 'Pengiriman gagal — kurir tidak dapat mengantarkan pesanan.',
            ],
        ]);
        $log->status = 'failed';
        $log->save();
    }
}
