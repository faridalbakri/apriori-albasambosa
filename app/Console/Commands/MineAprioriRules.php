<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\AprioriRule;
use App\Models\Order;
use App\Services\AprioriService;
use Illuminate\Console\Command;

class MineAprioriRules extends Command
{
    protected $signature = 'apriori:mine
                            {--minsupport= : Override min support threshold}
                            {--minconfidence= : Override min confidence threshold}
                            {--force : Skip 50-transaction guard}';

    protected $description = 'Run Apriori Market Basket Analysis on order data and save rules.';

    public function handle(AprioriService $apriori): int
    {
        $minSupport = $this->option('minsupport')
            ? (float) $this->option('minsupport')
            : config('apriori.min_support', 0.02);

        $minConfidence = $this->option('minconfidence')
            ? (float) $this->option('minconfidence')
            : config('apriori.min_confidence', 0.6);

        $force = (bool) $this->option('force');

        // --- Fetch valid orders (settlement+, constraint §5.2.1) ---
        $skippedStatuses = [
            OrderStatus::Cancel->value,
            OrderStatus::Expire->value,
            OrderStatus::Failed->value,
            OrderStatus::RefundPending->value,
            OrderStatus::RefundDone->value,
        ];

        $orderIds = Order::whereNotIn('status', $skippedStatuses)
            ->whereHas('items')
            ->pluck('id')
            ->toArray();

        $totalOrders = count($orderIds);

        $this->info("Found {$totalOrders} valid transactions.");

        if (! $force && $totalOrders < config('apriori.min_transactions', 50)) {
            $this->error("Need at least 50 transactions. Got: {$totalOrders}. Use --force to override.");

            return self::FAILURE;
        }

        // --- Build baskets from orders ---
        $this->info('Building baskets...');

        $baskets = [];
        $bar = $this->output->createProgressBar(count(array_chunk($orderIds, 500)));
        $bar->start();

        foreach (array_chunk($orderIds, 500) as $chunk) {
            Order::whereIn('id', $chunk)
                ->with('items')
                ->each(function (Order $order) use (&$baskets): void {
                    $productIds = $order->items
                        ->pluck('product_id')
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();

                    // Skip baskets with < 2 items — can't form association rules
                    if (count($productIds) >= 2) {
                        $baskets[] = $productIds;
                    }
                });
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Built '.count($baskets).' baskets from '.$totalOrders.' orders.');

        if (count($baskets) < 2) {
            $this->error('Not enough baskets with >= 2 items to generate rules.');

            return self::FAILURE;
        }

        // --- Run Apriori via Python mlxtend engine ---
        $this->info('Running Apriori mining...');

        // Clear old rules before mining (ensures DB reflects current params)
        AprioriRule::truncate();

        if ($force) {
            config(['apriori.min_transactions' => 1]); // bypass guard
        }

        $rules = $apriori->mine($baskets, $minSupport, $minConfidence);

        if (empty($rules)) {
            $this->info('No association rules met the thresholds.');

            return self::SUCCESS;
        }

        // --- Save to database ---
        // Store product IDs (not names) in antecedent/consequent JSON.
        // RecommendationService resolves names from IDs at query time,
        // so renaming a product doesn't silently break existing rules.
        $this->info('Saving '.count($rules).' rules to database...');

        $inserts = [];

        foreach ($rules as $rule) {
            $inserts[] = [
                'antecedent' => json_encode(array_values($rule['antecedent'])),
                'consequent' => json_encode(array_values($rule['consequent'])),
                'support' => $rule['support'],
                'confidence' => $rule['confidence'],
                'lift' => $rule['lift'],
            ];
        }

        // Batch insert in chunks of 500
        foreach (array_chunk($inserts, 500) as $chunk) {
            AprioriRule::insert($chunk);
        }

        $avgLift = collect($inserts)->avg('lift');

        $this->newLine();
        $this->info('✅ Apriori mining complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Transactions Analyzed', $totalOrders],
                ['Baskets (≥2 items)', count($baskets)],
                ['Engine', 'Python (mlxtend)'],
                ['Rules Generated', count($inserts)],
                ['Avg Lift', number_format($avgLift, 4)],
                ['Min Support', number_format($minSupport * 100, 1).'%'],
                ['Min Confidence', number_format($minConfidence * 100, 1).'%'],
            ]
        );

        return self::SUCCESS;
    }
}
