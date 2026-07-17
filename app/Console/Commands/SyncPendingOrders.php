<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Jobs\ProcessMidtransWebhook;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPendingOrders extends Command
{
    protected $signature = 'orders:sync-pending';

    protected $description = 'Check Midtrans for pending orders (10–55 min) and sync status.';

    public function handle(): int
    {
        $synced = 0;

        Order::where('status', OrderStatus::Pending->value)
            ->whereBetween('created_at', [now()->subMinutes(55), now()->subMinutes(10)])
            ->chunk(100, function ($orders) use (&$synced): void {
                foreach ($orders as $order) {
                    $status = MidtransService::getOrderStatus($order->order_number);

                    if (! $status || ! isset($status->transaction_status)) {
                        continue;
                    }

                    // Only sync non-pending statuses
                    if ($status->transaction_status === 'pending') {
                        continue;
                    }

                    ProcessMidtransWebhook::dispatch(
                        $order->order_number,
                        $status->transaction_status,
                        $status->fraud_status ?? 'accept',
                    );

                    $synced++;

                    Log::info('SyncPendingOrders: dispatched sync job', [
                        'order_number' => $order->order_number,
                        'midtrans_status' => $status->transaction_status,
                    ]);
                }
            });

        $this->info("Dispatched sync for {$synced} orders.");

        return self::SUCCESS;
    }
}
