<?php

namespace Database\Factories;

use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailedJob>
 */
class FailedJobFactory extends Factory
{
    private static array $jobs = [
        ['SendWhatsAppNotification', 'Twilio API error: 63018 — Rate limit exceeded after 3 retries'],
        ['SendWhatsAppNotification', 'Twilio API error: 21211 — Invalid phone number'],
        ['ProcessMidtransWebhook', 'InvalidStatusTransitionException: Cannot transition from pending to processing'],
        ['ProcessBiteshipWebhook', 'SQLSTATE[23000]: Duplicate entry for waybill_id'],
        ['ProcessMidtransWebhook', 'Midtrans API timeout after 30s'],
    ];

    public function definition(): array
    {
        $job = fake()->randomElement(self::$jobs);

        return [
            'uuid' => fake()->uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => fake()->uuid(),
                'displayName' => 'App\\Jobs\\'.$job[0],
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['command' => 'O:...'],
            ]),
            'exception' => $job[1],
            'failed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
