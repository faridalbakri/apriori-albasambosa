<?php

namespace App\Jobs;

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessMidtransWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $orderNumber,
        public string $transactionStatus,
        public string $fraudStatus,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TransitionOrderStatus $transitionOrderStatus): void
    {
        $order = Order::where('order_number', $this->orderNumber)
            ->with('items.product')
            ->first();

        if (! $order) {
            Log::warning('Midtrans webhook: order not found', [
                'order_number' => $this->orderNumber,
            ]);

            return;
        }

        // Secondary verification: confirm status via GET /v2/{order_id}/status
        $verified = MidtransService::getOrderStatus($this->orderNumber);

        if (! $verified || ! isset($verified->transaction_status)) {
            // Fallback to webhook payload (already signature-verified by controller)
            Log::warning('Midtrans webhook: secondary verification failed, falling back to payload', [
                'order_number' => $this->orderNumber,
            ]);

            $verifiedStatus = $this->transactionStatus;
            $verifiedFraud = $this->fraudStatus;
        } else {
            // Use verified status from Midtrans API (more reliable than webhook payload)
            $verifiedStatus = $verified->transaction_status;
            $verifiedFraud = $verified->fraud_status ?? 'accept';
        }

        // Map verified Midtrans status to OrderStatus
        $targetStatus = $this->mapToOrderStatus($verifiedStatus);

        if ($targetStatus === null) {
            Log::debug('Midtrans webhook: skipped (pending or unknown status)', [
                'order_number' => $this->orderNumber,
                'verified_status' => $verifiedStatus,
            ]);

            return;
        }

        // Fraud challenge on any successful payment status: treat as failed
        // Midtrans can send settlement/capture + fraud_status=challenge
        if ($verifiedFraud === 'challenge' && in_array($verifiedStatus, ['settlement', 'capture', 'deny'], true)) {
            // for V1, treat fraud challenge as failed (admin can manually resolve)
            $targetStatus = OrderStatus::Failed;
        }

        // Idempotency: skip if order is not Pending (already processed).
        // Exception: refund webhooks can arrive on already-processed orders.
        if ($order->status !== OrderStatus::Pending && $targetStatus !== OrderStatus::RefundPending) {
            Log::info('Midtrans webhook: order already processed (idempotent)', [
                'order_number' => $this->orderNumber,
                'current_status' => $order->status->value,
            ]);

            return;
        }

        try {
            $transitionOrderStatus($order, $targetStatus);

            Log::info('Midtrans webhook processed', [
                'order_number' => $this->orderNumber,
                'verified_status' => $verifiedStatus,
                'new_status' => $targetStatus->value,
            ]);
        } catch (InvalidStatusTransitionException $e) {
            Log::warning('Midtrans webhook: invalid transition (race condition or duplicate)', [
                'order_number' => $this->orderNumber,
                'current_status' => $order->status->value,
                'target_status' => $targetStatus->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map Midtrans transaction_status to our OrderStatus enum.
     * Returns null for 'pending' (no transition needed).
     */
    private function mapToOrderStatus(string $status): ?OrderStatus
    {
        return match ($status) {
            'settlement', 'capture' => OrderStatus::Settlement,
            'expire' => OrderStatus::Expire,
            'cancel' => OrderStatus::Cancel,
            'deny', 'failure' => OrderStatus::Failed,
            'refund', 'partial_refund' => OrderStatus::RefundPending,
            'pending' => null, // No transition — order stays Pending
            default => null,   // Unknown status — skip
        };
    }
}
