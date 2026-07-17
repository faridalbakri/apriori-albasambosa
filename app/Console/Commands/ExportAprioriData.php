<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\AprioriRule;
use App\Models\Order;
use Illuminate\Console\Command;

class ExportAprioriData extends Command
{
    protected $signature = 'apriori:export-data';

    protected $description = 'Export baskets and PHP rules as JSON for Python benchmarking.';

    public function handle(): int
    {
        $skippedStatuses = [
            OrderStatus::Cancel->value,
            OrderStatus::Expire->value,
            OrderStatus::Failed->value,
            OrderStatus::RefundPending->value,
            OrderStatus::RefundDone->value,
        ];

        // Export baskets
        $baskets = [];
        Order::whereNotIn('status', $skippedStatuses)
            ->whereHas('items')
            ->with('items')
            ->each(function (Order $order) use (&$baskets): void {
                $productIds = $order->items->pluck('product_id')
                    ->unique()->sort()->values()->toArray();
                if (count($productIds) >= 2) {
                    $baskets[] = $productIds;
                }
            });

        $dir = storage_path('app/apriori');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/baskets.json',
            json_encode($baskets, JSON_PRETTY_PRINT)
        );

        // Export PHP rules
        $rules = AprioriRule::orderByDesc('lift')->get()
            ->map(fn ($r) => [
                'antecedent' => $r->antecedent,
                'consequent' => $r->consequent,
            ])
            ->toArray();

        file_put_contents(
            $dir.'/php_rules.json',
            json_encode($rules, JSON_PRETTY_PRINT)
        );

        $this->info('Exported '.count($baskets).' baskets and '.count($rules).' rules.');
        $this->info('Files:');
        $this->info('  '.$dir.'/baskets.json');
        $this->info('  '.$dir.'/php_rules.json');

        return self::SUCCESS;
    }
}
