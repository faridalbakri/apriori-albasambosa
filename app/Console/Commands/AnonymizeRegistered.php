<?php

namespace App\Console\Commands;

use App\Enums\AnonymizationActionType;
use App\Models\User;
use App\Services\AnonymizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class AnonymizeRegistered extends Command
{
    protected $signature = 'privacy:anonymize-registered
                            {--dry-run : Show which users would be anonymized without executing}';

    protected $description = 'Anonymize registered users past retention period.';

    public function handle(AnonymizationService $service): int
    {
        $retentionMonths = (int) Cache::get('retention.registered_months', 36);
        $cutoff = AnonymizationService::getRetentionCutoff($retentionMonths);

        $query = User::whereNotNull('email_verified_at')
            ->where('role', '!=', 'admin')
            ->where('anonymization_exempt', false)
            ->whereNull('anonymized_at')
            ->whereRaw('COALESCE(last_login_at, created_at) < ?', [$cutoff]);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("DRY RUN: {$count} registered users would be anonymized.");

            $query->each(function (User $user) {
                $ref = $user->last_login_at ?? $user->created_at;
                $this->line("  - #{$user->id} {$user->email} | ref: {$ref->format('d M Y')}");
            });

            return self::SUCCESS;
        }

        $count = 0;
        $query->chunk(100, function ($users) use ($service, $retentionMonths, &$count) {
            foreach ($users as $user) {
                if (! $service->canAnonymizeUser($user, $retentionMonths)) {
                    continue;
                }

                $service->anonymizeUser($user, AnonymizationActionType::AutoAnonymizeRegistered);
                $count++;
            }
        });

        $this->info("Anonymized {$count} registered users.");

        return self::SUCCESS;
    }
}
