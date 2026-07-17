<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Happy-path flow used for status log generation.
     * Each entry is [from, to] where 'from' can be null for the initial state.
     */
    private const HAPPY_PATH = ['pending', 'settlement', 'processing', 'ready_pickup', 'shipping', 'delivered', 'completed'];

    public function run(): void
    {
        $batches = [
            [OrderStatus::Completed, 20],
            [OrderStatus::Pending, 3],
            [OrderStatus::Settlement, 3],
            [OrderStatus::Processing, 3],
            [OrderStatus::ReadyPickup, 2],
            [OrderStatus::Shipping, 3],
            [OrderStatus::Delivered, 3],
            [OrderStatus::Cancel, 3],
            [OrderStatus::Expire, 2],
            [OrderStatus::Failed, 2],
            [OrderStatus::RefundPending, 2],
            [OrderStatus::RefundDone, 1],
        ];

        $total = 0;
        $adminId = User::where('role', 'admin')->value('id'); // query instead of hardcode

        foreach ($batches as [$status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $method = fake()->boolean(70) ? 'pickup' : 'delivery';

                $order = Order::factory()
                    ->withItems(fake()->numberBetween(1, 4))
                    ->withStatus($status)
                    ->{$method}()
                    ->create();

                $date = match ($status) {
                    OrderStatus::Pending, OrderStatus::Settlement => now()->subDays(fake()->numberBetween(0, 2)),
                    OrderStatus::Completed, OrderStatus::RefundDone, OrderStatus::Expire => now()->subDays(fake()->numberBetween(15, 60)),
                    default => now()->subDays(fake()->numberBetween(3, 30)),
                };

                $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

                // Shipment for in-transit delivery orders
                if ($method === 'delivery' && in_array($status, [OrderStatus::Shipping, OrderStatus::Delivered, OrderStatus::Completed], true)) {
                    $isDelivered = $status === OrderStatus::Delivered || $status === OrderStatus::Completed;

                    Shipment::factory()
                        ->forOrder($order)
                        ->when($isDelivered, fn ($f) => $f->delivered())
                        ->create([
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                }

                // Status log — simulated audit trail
                $this->createStatusLogs($order, $status, $adminId);

                $total++;
            }
        }

        // Orders linked to registered customers — triggers anonimkan button visibility
        $customers = User::where('role', 'customer')->get();

        foreach ($customers as $customer) {
            // 1-4 completed orders per customer
            $orderCount = fake()->numberBetween(1, 4);
            for ($j = 0; $j < $orderCount; $j++) {
                $method = fake()->boolean(60) ? 'pickup' : 'delivery';
                $date = now()->subDays(fake()->numberBetween(5, 180));

                $order = Order::factory()
                    ->forUser($customer)
                    ->withItems(fake()->numberBetween(1, 3))
                    ->withStatus(OrderStatus::Completed)
                    ->{$method}()
                    ->create([
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                $this->createStatusLogs($order, OrderStatus::Completed, $adminId);

                if ($method === 'delivery') {
                    Shipment::factory()
                        ->forOrder($order)
                        ->delivered()
                        ->create([
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                }

                $total++;
            }
        }

        $this->command->info("Seeded {$total} orders with varied dates and all 12 statuses.");
    }

    /**
     * Generate realistic status log entries from 'pending' to the final status.
     */
    private function createStatusLogs(Order $order, OrderStatus $finalStatus, int $adminId): void
    {
        $path = $this->pathTo($finalStatus);
        $timestamp = $order->created_at->copy();
        $prev = null;

        foreach ($path as $step) {
            OrderStatusLog::create([
                'order_id' => $order->id,
                'old_status' => $prev,
                'new_status' => $step,
                'user_id' => $prev === null ? null : $adminId, // initial status = system, rest = admin
                'created_at' => $timestamp,
            ]);

            $prev = $step;
            $timestamp = $timestamp->addMinutes(fake()->numberBetween(10, 1440));
        }
    }

    /**
     * Build the status path from 'pending' to the target status.
     */
    private function pathTo(OrderStatus $target): array
    {
        $targetValue = $target->value;

        // Happy path — take the prefix up to the target
        $idx = array_search($targetValue, self::HAPPY_PATH);
        if ($idx !== false) {
            return array_slice(self::HAPPY_PATH, 0, $idx + 1);
        }

        // Terminal states that branch off the happy path
        return match ($targetValue) {
            'expire' => ['pending', 'expire'],
            'cancel' => ['pending', 'settlement', 'cancel'],
            'failed' => ['pending', 'settlement', 'processing', 'failed'],
            'refund_pending' => ['pending', 'settlement', 'processing', 'ready_pickup', 'shipping', 'delivered', 'refund_pending'],
            'refund_done' => ['pending', 'settlement', 'processing', 'ready_pickup', 'shipping', 'delivered', 'refund_pending', 'refund_done'],
            default => ['pending', $targetValue],
        };
    }
}
