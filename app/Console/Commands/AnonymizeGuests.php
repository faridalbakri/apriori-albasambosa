<?php

namespace App\Console\Commands;

use App\Enums\AnonymizationActionType;
use App\Models\AnonymizationLog;
use App\Models\Order;
use App\Services\AnonymizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnonymizeGuests extends Command
{
    protected $signature = 'privacy:anonymize-guests
                            {--dry-run : Show which orders would be anonymized without executing}';

    protected $description = 'Anonymize phone numbers on expired guest orders (no User record).';

    public function handle(): int
    {
        $retentionMonths = (int) Cache::get('retention.guest_months', 24);
        $cutoff = AnonymizationService::getRetentionCutoff($retentionMonths);

        $query = Order::whereNull('user_id')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('phone')
            ->whereNotIn('status', AnonymizationService::ACTIVE_STATUSES);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("DRY RUN: {$count} guest orders would be anonymized.");

            $query->each(function (Order $order) {
                $this->line("  - {$order->order_number} | {$order->created_at->format('d M Y')} | {$order->phone}");
            });

            return self::SUCCESS;
        }

        $count = 0;
        $query->chunk(100, function ($orders) use (&$count) {
            foreach ($orders as $order) {
                DB::transaction(function () use ($order, &$count) {
                    $anonymized = ['orders.phone'];
                    $order->phone = null;

                    // Also clear PII fields if they exist on guest orders
                    if ($order->recipient_name) {
                        $order->recipient_name = null;
                        $anonymized[] = 'orders.recipient_name';
                    }
                    if ($order->address_detail) {
                        $order->address_detail = null;
                        $anonymized[] = 'orders.address_detail';
                    }

                    $order->save();

                    $log = new AnonymizationLog;
                    $log->user_id = null;
                    $log->action_type = AnonymizationActionType::AutoAnonymizeGuest->value;
                    $log->anonymized_fields = $anonymized;
                    $log->save();

                    $count++;
                });
            }
        });

        $this->info("Anonymized {$count} guest orders.");

        return self::SUCCESS;
    }
}
