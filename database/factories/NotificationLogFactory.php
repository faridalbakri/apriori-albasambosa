<?php

namespace Database\Factories;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    public function definition(): array
    {
        $orderNumber = sprintf('ALBA-%s-%03d', now()->subDays(fake()->numberBetween(0, 30))->format('Ymd'), fake()->numberBetween(1, 200));

        return [
            'user_id' => User::factory(),
            'metadata' => [
                'order_id' => fake()->numberBetween(1, 200),
                'order_number' => $orderNumber,
                'status' => fake()->randomElement(['ready_pickup', 'delivered']),
                'channel' => 'whatsapp',
                'phone' => '+628'.fake()->numerify('##########'),
            ],
            'status' => 'sent',
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $metadata = $attributes['metadata'];
            $metadata['error'] = fake()->randomElement([
                'Twilio API error: 63018 — Rate limit exceeded',
                'Twilio API error: 21211 — Invalid phone number',
                'Twilio API error: 63016 — Sandbox phone number not verified',
            ]);

            return [
                'status' => 'failed',
                'metadata' => $metadata,
            ];
        });
    }

    public function biteshipFailed(): static
    {
        $orderNumber = sprintf('ALBA-%s-%03d', now()->subDays(fake()->numberBetween(0, 30))->format('Ymd'), fake()->numberBetween(1, 200));

        return $this->state([
            'metadata' => [
                'order_id' => fake()->numberBetween(1, 200),
                'order_number' => $orderNumber,
                'channel' => 'biteship',
                'waybill_id' => strtoupper(fake()->bothify('???-####-???')),
                'status' => 'failed',
                'error' => 'Pengiriman gagal — kurir tidak dapat mengantarkan pesanan.',
            ],
            'status' => 'failed',
        ]);
    }
}
