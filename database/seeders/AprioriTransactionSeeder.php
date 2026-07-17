<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AprioriTransactionSeeder extends Seeder
{
    private array $bundles = [
        [['Sambosa Original Frozen', 'Air Mineral'], 40],
        [['Sambosa Original Frozen', 'Saus Mentai'], 30],
        [['Sambosa Smoked Beef Frozen', 'Air Mineral'], 25],
        [['Sambosa Smoked Beef Frozen', 'Saus Sambal'], 20],
        [['Kroket Frozen', 'Saus Mentai'], 20],
        [['Kroket Frozen', 'Saus Sambal'], 15],
        [['Pastel Ayam Frozen', 'Saus Mentai'], 15],
        [['Risol Rougut Frozen', 'Saus Sambal'], 15],
        [['Roti Maryam Frozen', 'Susu Kurma'], 10],
        [['Sambosa Original', 'Teh Botol'], 25],
        [['Sambosa Original', 'Saus Sambal'], 20],
        [['Sambosa Smoked Beef', 'Saus Mentai'], 20],
        [['Risol Rougut', 'Saus Sambal'], 15],
        [['Pastel Ayam', 'Saus Mentai'], 15],
        [['Kroket', 'Teh Botol'], 10],
        [['Roti Maryam', 'Susu Kurma'], 10],
        [['Sambosa Original Frozen', 'Sambosa Smoked Beef Frozen', 'Saus Mentai'], 10],
        [['Sambosa Original Frozen', 'Kroket Frozen'], 10],
        [['Air Mineral', 'Teh Botol'], 15],
        [['Saus Mentai', 'Saus Sambal'], 10],
    ];

    public function run(): void
    {
        $this->command->info('Generating 200 Apriori transactions...');

        $products = Product::pluck('id', 'name')->toArray();
        $adminId = DB::table('users')->where('role', 'admin')->value('id') ?? 1;
        $now = now();

        DB::transaction(function () use ($products, $adminId, $now) {
            for ($i = 0; $i < 200; $i++) {
                $orderDate = $now->copy()->subDays(rand(0, 90));

                $orderId = DB::table('orders')->insertGetId([
                    'user_id' => $adminId,
                    'order_number' => 'ALBA-'.$orderDate->format('Ymd').'-'.str_pad($i + 1000, 3, '0', STR_PAD_LEFT),
                    'recipient_name' => 'Customer '.($i + 1),
                    'phone' => '081234567'.str_pad($i % 100, 2, '0', STR_PAD_LEFT),
                    'status' => 'completed',
                    'total_price' => 0,
                    'shipping_cost' => 0,
                    'created_at' => $orderDate,
                    'updated_at' => $orderDate,
                ]);

                $itemIds = [];
                $bundleCount = $this->weightedRandom([1 => 30, 2 => 50, 3 => 20]);

                for ($j = 0; $j < $bundleCount; $j++) {
                    $bundle = $this->pickWeightedBundle();
                    foreach ($bundle as $productName) {
                        $pid = $products[$productName] ?? null;
                        if ($pid) {
                            $itemIds[$pid] = ($itemIds[$pid] ?? 0) + 1;
                        }
                    }
                }

                $totalPrice = 0;
                foreach ($itemIds as $productId => $quantity) {
                    $price = Product::find($productId)->price;
                    $totalPrice += $price * $quantity;

                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $productId,
                        'price' => $price,
                        'quantity' => $quantity,
                    ]);
                }

                DB::table('orders')->where('id', $orderId)->update(['total_price' => $totalPrice]);
            }
        });

        $basketCount = DB::table('order_items')
            ->selectRaw('order_id, count(*) as items')
            ->groupBy('order_id')
            ->having('items', '>=', 2)
            ->count();

        $this->command->info("Created 200 orders, {$basketCount} baskets with >= 2 items.");
    }

    private function pickWeightedBundle(): array
    {
        $total = array_sum(array_column($this->bundles, 1));
        $rand = rand(1, $total);
        $cumulative = 0;

        foreach ($this->bundles as [$items, $weight]) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $items;
            }
        }

        return $this->bundles[0][0];
    }

    private function weightedRandom(array $weights): int
    {
        $total = array_sum($weights);
        $rand = rand(1, $total);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $value;
            }
        }

        return 2;
    }
}
