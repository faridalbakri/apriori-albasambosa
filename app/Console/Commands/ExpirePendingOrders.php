<?php

namespace App\Console\Commands;

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending';

    protected $description = 'Sync pending orders > 1 hour with Midtrans, expire if unpaid.';

    public function handle(TransitionOrderStatus $transitionOrderStatus): int
    {
        $synced = 0;
        $expired = 0;

        Order::where('status', OrderStatus::Pending->value)
            ->where('created_at', '<', now()->subHours(1))
            ->with('items.product')
            ->chunk(100, function ($orders) use ($transitionOrderStatus, &$synced, &$expired): void {
                foreach ($orders as $order) {
                    // Check Midtrans first — customer may have paid
                    // but webhook failed to arrive.
                    $status = MidtransService::getOrderStatus($order->order_number);

                    if ($status && isset($status->transaction_status)
                        && $status->transaction_status !== 'pending') {
                        // Midtrans has a non-pending status — sync it via webhook job
                        ProcessMidtransWebhook::dispatch(
                            $order->order_number,
                            $status->transaction_status,
                            $status->fraud_status ?? 'accept',
                        );
                        $synced++;
                    } else {
                        // No payment detected — expire the order
                        try {
                            $transitionOrderStatus($order, OrderStatus::Expire);
                            $expired++;
                        } catch (InvalidStatusTransitionException $e) {
                            Log::warning('ExpirePendingOrders: failed to expire order', [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'current_status' => $order->status->value,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });

        $this->info("Synced {$synced} orders from Midtrans, expired {$expired} unpaid orders.");

        return self::SUCCESS;
    }
}
