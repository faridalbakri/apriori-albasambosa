<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TransitionOrderStatus
{
    public const VALID_TRANSITIONS = [
        'pending' => ['settlement', 'expire', 'cancel', 'failed'],
        'settlement' => ['processing'],
        'processing' => ['ready_pickup', 'shipping', 'failed', 'cancel', 'refund_pending'],
        'ready_pickup' => ['completed', 'failed', 'refund_pending'],
        'shipping' => ['delivered', 'cancel', 'failed', 'refund_pending'],
        'delivered' => ['completed', 'refund_pending'],
        'cancel' => ['refund_pending'],
        'refund_pending' => ['refund_done'],
    ];

    /**
     * Stock effects per target status:
     * - deduct_reserved: subtract stock_reserved from physical stock
     * - release_reserved: return stock_reserved to available pool (pre-deduction only)
     * - restore_stock: return physical stock + stock_reserved (post-deduction cancel/fail)
     */
    private const STOCK_EFFECTS = [
        'settlement' => 'deduct_reserved',
        'expire' => 'release_reserved',
        'cancel' => 'release_reserved',
        'failed' => 'release_reserved',
    ];

    /**
     * States where physical stock has already been deducted.
     * Cancel/fail from these states must restore stock, not release reserved.
     */
    private const POST_DEDUCTION_STATES = [
        'settlement', 'processing', 'ready_pickup', 'shipping', 'delivered',
    ];

    /**
     * Execute a status transition with stock effects and audit trail.
     */
    public function __invoke(
        Order $order,
        OrderStatus $newStatus,
        ?User $actor = null,
    ): Order {
        $newValue = $newStatus->value;

        // 1) Validate transition (pre-check, re-verified inside transaction)
        $allowed = self::VALID_TRANSITIONS[$order->status->value] ?? [];

        if (! in_array($newValue, $allowed, true)) {
            throw new InvalidStatusTransitionException($order->status, $newStatus);
        }

        // 2) Apply in DB transaction: pessimistic lock + re-verify + stock effects + audit
        DB::transaction(function () use ($order, $newValue, $actor) {
            // Lock the order row to prevent race conditions with scheduler/webhook
            $locked = Order::where('id', $order->id)->with('items.product')->lockForUpdate()->firstOrFail();
            $currentStatus = $locked->status;

            // Re-verify transition with fresh DB state
            $allowed = self::VALID_TRANSITIONS[$currentStatus->value] ?? [];

            if (! in_array($newValue, $allowed, true)) {
                throw new InvalidStatusTransitionException($currentStatus, OrderStatus::from($newValue));
            }

            // Auto-cancel before refund from processing/ready_pickup/shipping
            if ($newValue === 'refund_pending' && ! in_array($currentStatus->value, ['delivered', 'cancel'], true)) {
                $this->applyStockEffect($locked, $currentStatus->value, 'cancel');

                OrderStatusLog::create([
                    'order_id' => $locked->id,
                    'old_status' => $currentStatus->value,
                    'new_status' => 'cancel',
                    'user_id' => $actor?->id,
                ]);

                // Keep rolling: set status to cancel first, then refund_pending below
                $locked->status = 'cancel';
                $currentStatus = OrderStatus::Cancel;
            }

            $this->applyStockEffect($locked, $currentStatus->value, $newValue);

            $locked->status = $newValue;
            $locked->save();

            OrderStatusLog::create([
                'order_id' => $locked->id,
                'old_status' => $currentStatus->value,
                'new_status' => $newValue,
                'user_id' => $actor?->id,
            ]);
        });

        $order = $order->fresh();

        // Dispatch WhatsApp notification for key milestones
        if (in_array($newStatus, [
            OrderStatus::Settlement,
            OrderStatus::Shipping,
            OrderStatus::ReadyPickup,
            OrderStatus::Delivered,
        ], true)) {
            dispatch(new SendWhatsAppNotification($order->id));
        }

        // Invalidate catalog cache when total_sold changes (settlement).
        // Best-seller rankings and Apriori recommendations depend on sales data.
        if ($newStatus === OrderStatus::Settlement) {
            Cache::increment('catalog_version');
        }

        return $order;
    }

    private function applyStockEffect(Order $order, string $oldStatus, string $newStatus): void
    {
        $effect = self::STOCK_EFFECTS[$newStatus] ?? null;

        if ($effect === null) {
            return;
        }

        // Cancel/fail from post-deduction states must restore stock,
        // not just release reserved (which was already consumed at settlement).
        if ($effect === 'release_reserved' && in_array($oldStatus, self::POST_DEDUCTION_STATES, true)) {
            $effect = 'restore_stock';
        }

        foreach ($order->items as $item) {
            /** @var Product $product */
            $product = $item->product;

            if ($effect === 'deduct_reserved') {
                $product->decrement('stock', $item->quantity);
                $product->decrement('stock_reserved', $item->quantity);
                $product->increment('total_sold', $item->quantity);
            }

            if ($effect === 'release_reserved') {
                $product->decrement('stock_reserved', $item->quantity);
            }

            if ($effect === 'restore_stock') {
                // Only restore physical stock — stock_reserved was already
                // decremented to 0 by deduct_reserved at settlement.
                // Re-incrementing it would create phantom reservations.
                $product->increment('stock', $item->quantity);
            }
        }
    }
}
