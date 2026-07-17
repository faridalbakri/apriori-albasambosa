<?php

namespace App\Services;

use App\Enums\AnonymizationActionType;
use App\Models\AnonymizationLog;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AnonymizationService
{
    public const ACTIVE_STATUSES = [
        'pending',
        'settlement',
        'processing',
        'ready_pickup',
        'shipping',
    ];

    public const GRACE_PERIOD_DAYS = 7;

    /**
     * Get the cutoff date for retention-based anonymization.
     * Single source of truth — commands call this instead of duplicating
     * the COALESCE/subMonths/subDays logic.
     */
    public static function getRetentionCutoff(int $retentionMonths): Carbon
    {
        return now()->subMonths($retentionMonths)->subDays(self::GRACE_PERIOD_DAYS);
    }

    /**
     * Check whether a user can be anonymized.
     *
     * @param  bool  $skipRetentionCheck  If true, skip the retention period + grace
     *                                    period check. Used for manual "Hak Dilupakan" where the customer
     *                                    explicitly requests deletion — only the safety lock applies.
     */
    public function canAnonymizeUser(User $user, int $retentionMonths, bool $skipRetentionCheck = false): bool
    {
        if ($user->anonymized_at !== null) {
            return false;
        }

        if (str_starts_with($user->email, 'anon_') && str_ends_with($user->email, '@deleted.local')) {
            return false;
        }

        $hasActive = $user->orders()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->exists();

        if ($hasActive) {
            return false;
        }

        if ($skipRetentionCheck) {
            return true;
        }

        $referenceDate = $user->last_login_at ?? $user->created_at;
        $cutoff = now()->subMonths($retentionMonths)->subDays(self::GRACE_PERIOD_DAYS);

        return $referenceDate->lt($cutoff);
    }

    public function anonymizeUser(
        User $user,
        AnonymizationActionType $type,
    ): void {
        DB::transaction(function () use ($user, $type) {
            // Refresh to guard against stale in-memory objects that passed
            // canAnonymizeUser() before another process anonymized the same user.
            $user->refresh();

            if ($user->anonymized_at !== null) {
                return; // already anonymized by another process
            }

            $user->name = 'Anonymized User';
            $user->email = "anon_{$user->id}@deleted.local";
            $user->password = Hash::make(Str::random(40));
            $user->remember_token = null;
            $user->anonymized_at = now();
            $user->save();

            $user->addresses()->update([
                'recipient_name' => 'Anonymized',
                'phone' => null,
            ]);

            Cart::where('user_id', $user->id)->delete();

            $log = new AnonymizationLog;
            $log->user_id = $user->id;
            $log->action_type = $type->value;
            $log->anonymized_fields = [
                'name',
                'email',
                'password',
                'remember_token',
                'addresses.recipient_name',
                'addresses.phone',
            ];
            $log->save();
        });
    }
}
