<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected static array $couriers = [
        ['company' => 'GoSend', 'service' => 'instant'],
        ['company' => 'GrabExpress', 'service' => 'instant'],
    ];

    public function definition(): array
    {
        $courier = fake()->randomElement(self::$couriers);

        return [
            'order_id' => Order::factory(),
            'waybill_id' => strtoupper(fake()->bothify('???-####-???')),
            'courier' => $courier['company'],
            'courier_service' => $courier['service'],
            'tracking_status' => fake()->randomElement(['created', 'allocated', 'picking_up', 'on_delivery', 'delivered']),
            'estimated_arrival' => fake()->dateTimeBetween('now', '+2 days'),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(['order_id' => $order->id]);
    }

    public function delivered(): static
    {
        return $this->state([
            'tracking_status' => 'delivered',
            'estimated_arrival' => now()->subHours(fake()->numberBetween(1, 24)),
        ]);
    }
}
